<?php

declare(strict_types=1);

use App\Actions\Reports\GenerateSupportThemes;
use App\Enums\DeflectionOutcome;
use App\Models\InAppNotification;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketDeflection;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\AiResult;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $this->fake = new FakeAiClient;
    $this->fake->reply = 'Payments drove most of last week. Four tickets repeated an open one, which means the first answer did not land. Write a clear EMI-failure policy.';
    app()->instance(AiClient::class, $this->fake);
});

it('aggregates the week and narrates it for the admin', function () {
    Ticket::factory()->count(3)->for($this->tenant)->create([
        'student_id' => $this->student->id, 'category' => 'payments', 'created_at' => now()->subDays(2),
    ]);
    Ticket::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'category' => 'technical', 'created_at' => now()->subDays(3),
        'ai_sentiment' => 'angry', 'csat_rating' => 4,
    ]);

    $report = app(GenerateSupportThemes::class)->handle($this->admin);

    expect($report)->not->toBeNull()
        ->and($report->data['total'])->toBe(4)
        ->and($report->data['by_category']['payments'])->toBe(3)
        ->and($report->data['distressed'])->toBe(1)
        // toEqual, not toBe: a whole average round-trips through JSON as an int.
        ->and($report->data['csat_avg'])->toEqual(4)
        ->and($report->narrative)->toContain('Payments drove most of last week');

    // Counts are computed, not asked for — the model only ever sees them.
    expect($this->fake->calls[0]->user)->toContain('payments 3');

    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $this->admin->id)->count())->toBe(1);
});

it('reports the deflection accept rate — the read on the corpus', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'created_at' => now()->subDay()]);

    TicketDeflection::factory()->count(3)->for($this->tenant)->create([
        'student_id' => $this->student->id, 'outcome' => DeflectionOutcome::Accepted->value, 'created_at' => now()->subDay(),
    ]);
    TicketDeflection::factory()->for($this->tenant)->create([
        'student_id' => $this->student->id, 'outcome' => DeflectionOutcome::Proceeded->value, 'created_at' => now()->subDay(),
    ]);

    $report = app(GenerateSupportThemes::class)->handle($this->admin);

    expect($report->data['deflection']['offered'])->toBe(4)
        ->and($report->data['deflection']['accepted'])->toBe(3)
        ->and($report->data['deflection']['accept_pct'])->toBe(75);

    expect($this->fake->calls[0]->user)->toContain('75% resolved without a ticket');
});

it('distinguishes never offering an answer from offering one that never lands', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'created_at' => now()->subDay()]);

    $report = app(GenerateSupportThemes::class)->handle($this->admin);

    expect($report->data['deflection']['accept_pct'])->toBeNull();
    expect($this->fake->calls[0]->user)->toContain('no answers were offered');
});

it('says nothing when there were no tickets', function () {
    expect(app(GenerateSupportThemes::class)->handle($this->admin))->toBeNull()
        ->and($this->fake->calls)->toBeEmpty();
});

it('ignores tickets outside the week', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'created_at' => now()->subDay()]);
    Ticket::factory()->count(5)->for($this->tenant)->create([
        'student_id' => $this->student->id, 'created_at' => now()->subMonth(),
    ]);

    expect(app(GenerateSupportThemes::class)->handle($this->admin)->data['total'])->toBe(1);
});

it('never counts another tenant tickets', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'created_at' => now()->subDay()]);

    $other = Tenant::factory()->create();
    Ticket::factory()->count(4)->for($other)->create(['created_at' => now()->subDay()]);

    expect(app(GenerateSupportThemes::class)->handle($this->admin)->data['total'])->toBe(1);
});

it('is idempotent for the week', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'created_at' => now()->subDay()]);

    app(GenerateSupportThemes::class)->handle($this->admin);
    app(GenerateSupportThemes::class)->handle($this->admin);

    expect(Report::withoutGlobalScopes()->where('type', 'support_themes')->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $this->admin->id)->count())->toBe(1);
});

it('still delivers the counts when the model is down', function () {
    Ticket::factory()->count(2)->for($this->tenant)->create([
        'student_id' => $this->student->id, 'category' => 'payments', 'created_at' => now()->subDay(),
    ]);
    app()->instance(AiClient::class, new class implements AiClient
    {
        public function complete(AiMessage $m): AiResult
        {
            throw new RuntimeException('upstream down');
        }
    });

    $report = app(GenerateSupportThemes::class)->handle($this->admin);

    // The interpretation is lost; the facts are not.
    expect($report)->not->toBeNull()
        ->and($report->narrative)->toContain('2 ticket(s)')
        ->and($report->ai_event_id)->toBeNull()
        ->and($report->data['by_category']['payments'])->toBe(2);
});

it('sends a report per admin via the scheduled command', function () {
    Ticket::factory()->for($this->tenant)->create(['student_id' => $this->student->id, 'created_at' => now()->subDay()]);

    $this->artisan('digest:support-themes')->assertSuccessful();

    expect(Report::withoutGlobalScopes()->where('type', 'support_themes')->where('recipient_id', $this->admin->id)->count())->toBe(1);
});
