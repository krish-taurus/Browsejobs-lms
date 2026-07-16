<?php

declare(strict_types=1);

use App\Actions\Support\CreateTicket;
use App\Actions\Support\PostTicketReply;
use App\Actions\Support\ReopenTicket;
use App\Actions\Support\SubmitCsat;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Jobs\CheckTicketSla;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\SupportTeam;
use App\Models\Tenant;
use App\Models\TicketMessage;
use App\Models\TicketRoute;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 70001']);
});

/**
 * A `team`-routed category with a team of $count agents.
 *
 * @return array{route: TicketRoute, team: SupportTeam, agents: list<User>}
 */
function techRoute(Tenant $tenant, int $count = 1): array
{
    $team = SupportTeam::query()->create(['tenant_id' => $tenant->id, 'name' => 'Tech', 'slug' => 'technical', 'category' => 'technical']);
    $agents = [];
    for ($i = 0; $i < $count; $i++) {
        $agent = User::factory()->for($tenant)->create(['user_type' => 'staff']);
        $team->members()->attach($agent->id);
        $agents[] = $agent;
    }
    $route = TicketRoute::factory()->for($tenant)->create([
        'category' => TicketCategory::Technical->value, 'routing' => TicketRoute::ROUTING_TEAM,
        'support_team_id' => $team->id, 'first_response_minutes' => 240, 'resolution_minutes' => 2880,
    ]);

    return ['route' => $route, 'team' => $team, 'agents' => $agents];
}

it('creates a ticket, routes it to the team, sets SLA due-times and arms the SLA jobs', function () {
    Queue::fake();
    ['agents' => $agents] = techRoute($this->tenant);

    $ticket = app(CreateTicket::class)->handle($this->student, TicketCategory::Technical, 'Cannot log in', 'It keeps erroring on submit.');

    expect($ticket->assignee_id)->toBe($agents[0]->id)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->reference)->toStartWith('BJ-')
        ->and((int) $ticket->created_at->diffInMinutes($ticket->first_response_due_at))->toBe(240)
        ->and(TicketMessage::withoutGlobalScopes()->where('ticket_id', $ticket->id)->count())->toBe(1);

    Queue::assertPushed(CheckTicketSla::class, 3);
});

it('round-robins assignment across team members', function () {
    ['agents' => $agents] = techRoute($this->tenant, 2);
    $second = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 70002']);

    $a = app(CreateTicket::class)->handle($this->student, TicketCategory::Technical, 'A', 'first issue here');
    $b = app(CreateTicket::class)->handle($second, TicketCategory::Technical, 'B', 'second issue here');

    expect($a->assignee_id)->toBe($agents[0]->id)
        ->and($b->assignee_id)->toBe($agents[1]->id);
});

it('routes Training to the batch trainer, falling back to the team', function () {
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $team = SupportTeam::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Trainers', 'slug' => 'training', 'category' => 'training']);
    $fallback = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $team->members()->attach($fallback->id);
    TicketRoute::factory()->for($this->tenant)->create([
        'category' => TicketCategory::Training->value, 'routing' => TicketRoute::ROUTING_BATCH_TRAINER,
        'support_team_id' => $team->id,
    ]);

    $course = Course::factory()->for($this->tenant)->create();
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value, 'trainer_id' => $trainer->id]);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => BatchMemberStatus::Enrolled->value]);

    $withTrainer = app(CreateTicket::class)->handle($this->student, TicketCategory::Training, 'Doubt', 'a training doubt here');
    expect($withTrainer->assignee_id)->toBe($trainer->id);

    // A student with no trainer-bearing batch falls back to the team.
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 70003']);
    $fell = app(CreateTicket::class)->handle($other, TicketCategory::Training, 'Doubt2', 'another training doubt');
    expect($fell->assignee_id)->toBe($fallback->id);
});

it('sets first_response_at and In Progress on a staff reply; a student reply does not', function () {
    ['agents' => $agents] = techRoute($this->tenant);
    $ticket = app(CreateTicket::class)->handle($this->student, TicketCategory::Technical, 'Hi', 'need help please');

    app(PostTicketReply::class)->handle($ticket, $agents[0], 'On it — can you retry?');
    $ticket->refresh();

    expect($ticket->first_response_at)->not->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::InProgress);

    // An internal note leaves first_response_at as-is and adds a message.
    app(PostTicketReply::class)->handle($ticket, $agents[0], 'internal: escalate if no reply', true);
    expect(TicketMessage::withoutGlobalScopes()->where('ticket_id', $ticket->id)->where('is_internal', true)->count())->toBe(1);
});

it('reopens within the window and blocks after it', function () {
    techRoute($this->tenant);
    $ticket = app(CreateTicket::class)->handle($this->student, TicketCategory::Technical, 'X', 'issue text here');
    $ticket->update(['status' => TicketStatus::Resolved->value, 'resolved_at' => now()->subDays(2)]);

    app(ReopenTicket::class)->handle($ticket->fresh());
    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);

    $ticket->update(['status' => TicketStatus::Resolved->value, 'resolved_at' => now()->subDays(10)]);
    expect(fn () => app(ReopenTicket::class)->handle($ticket->fresh()))
        ->toThrow(ValidationException::class);
});

it('records a CSAT rating on a resolved ticket', function () {
    techRoute($this->tenant);
    $ticket = app(CreateTicket::class)->handle($this->student, TicketCategory::Technical, 'X', 'issue text here');
    $ticket->update(['status' => TicketStatus::Resolved->value, 'resolved_at' => now()]);

    app(SubmitCsat::class)->handle($ticket->fresh(), 5, 'Fast and clear.');

    expect($ticket->fresh()->csat_rating)->toBe(5);
});
