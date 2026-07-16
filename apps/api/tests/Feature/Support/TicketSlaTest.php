<?php

declare(strict_types=1);

use App\Actions\Support\EscalateTicket;
use App\Actions\Support\NotifyTicket;
use App\Actions\Support\RunTicketSlaSweep;
use App\Enums\TicketStatus;
use App\Jobs\CheckTicketSla;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->agent = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
});

function runTicketSla(int $ticketId, string $phase): void
{
    (new CheckTicketSla($ticketId, $phase))->handle(app(NotifyTicket::class), app(EscalateTicket::class));
}

it('warns the assignee at the 75% mark', function () {
    $ticket = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'assignee_id' => $this->agent->id,
        'first_response_due_at' => now()->addMinutes(240),
    ]);

    $this->travelTo(now()->addMinutes(185));
    runTicketSla($ticket->id, 'warning');

    expect($ticket->fresh()->response_warned_at)->not->toBeNull();
});

it('escalates to an admin on first-response breach, idempotently, with an audit', function () {
    $ticket = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'assignee_id' => $this->agent->id,
        'first_response_due_at' => now()->subMinute(),
    ]);

    runTicketSla($ticket->id, 'first_response');
    $ticket->refresh();

    expect($ticket->breached_at)->not->toBeNull()
        ->and($ticket->escalated_at)->not->toBeNull()
        ->and($ticket->assignee_id)->toBe($this->admin->id)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'ticket.escalated')->count())->toBe(1);

    runTicketSla($ticket->id, 'first_response');
    expect(AuditLog::withoutGlobalScopes()->where('action', 'ticket.escalated')->count())->toBe(1);
});

it('escalates on resolution breach', function () {
    $ticket = Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'assignee_id' => $this->agent->id,
        'first_response_at' => now(), 'resolution_due_at' => now()->subMinute(),
    ]);

    runTicketSla($ticket->id, 'resolution');

    expect($ticket->fresh()->resolution_breached_at)->not->toBeNull()
        ->and($ticket->fresh()->escalated_at)->not->toBeNull();
});

it('does not act on a resolved ticket', function () {
    $ticket = Ticket::factory()->for($this->tenant)->resolved()->create([
        'student_id' => $this->student->id, 'first_response_due_at' => now()->subMinute(),
    ]);

    runTicketSla($ticket->id, 'first_response');

    expect($ticket->fresh()->breached_at)->toBeNull();
});

it('sweep re-dispatches checks for overdue tickets', function () {
    Queue::fake();
    Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'status' => TicketStatus::Open->value,
        'first_response_due_at' => now()->subHour(),
    ]);

    $summary = app(RunTicketSlaSweep::class)->handle();

    expect($summary['swept'])->toBe(1);
    Queue::assertPushed(CheckTicketSla::class);
});
