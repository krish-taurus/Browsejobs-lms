<?php

declare(strict_types=1);

use App\Actions\Crm\RecordTimelineEvent;
use App\Jobs\CheckLeadSla;
use App\Models\ContactTimelineEvent;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->counselor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
});

function runSla(int $leadId): void
{
    (new CheckLeadSla($leadId))->handle(app(RecordTimelineEvent::class));
}

it('flags a breach and drops an SLA task when a lead is untouched past due', function () {
    $lead = Lead::factory()->for($this->tenant)->create([
        'assigned_to' => $this->counselor->id,
        'first_touch_at' => null,
        'sla_due_at' => now()->subMinute(),
        'sla_breached_at' => null,
    ]);

    runSla($lead->id);

    expect($lead->fresh()->sla_breached_at)->not->toBeNull();

    $task = CrmTask::withoutGlobalScopes()->where('lead_id', $lead->id)->where('source', 'sla')->first();
    expect($task)->not->toBeNull()
        ->and($task->assigned_to)->toBe($this->counselor->id);

    expect(ContactTimelineEvent::withoutGlobalScopes()
        ->where('lead_id', $lead->id)->where('type', ContactTimelineEvent::TYPE_SLA_BREACHED)->count())->toBe(1);
});

it('does not breach a lead that has already been touched', function () {
    $lead = Lead::factory()->for($this->tenant)->create([
        'assigned_to' => $this->counselor->id,
        'first_touch_at' => now()->subMinutes(2),
        'sla_due_at' => now()->subMinute(),
    ]);

    runSla($lead->id);

    expect($lead->fresh()->sla_breached_at)->toBeNull()
        ->and(CrmTask::withoutGlobalScopes()->where('lead_id', $lead->id)->count())->toBe(0);
});

it('does not breach before the SLA is due (sync-driver guard)', function () {
    $lead = Lead::factory()->for($this->tenant)->create([
        'assigned_to' => $this->counselor->id,
        'first_touch_at' => null,
        'sla_due_at' => now()->addMinutes(15),
    ]);

    runSla($lead->id);

    expect($lead->fresh()->sla_breached_at)->toBeNull();
});

it('is idempotent — re-running after a breach does not duplicate the task', function () {
    $lead = Lead::factory()->for($this->tenant)->create([
        'assigned_to' => $this->counselor->id,
        'first_touch_at' => null,
        'sla_due_at' => now()->subMinute(),
    ]);

    runSla($lead->id);
    $breachedAt = $lead->fresh()->sla_breached_at;
    runSla($lead->id);

    expect(CrmTask::withoutGlobalScopes()->where('lead_id', $lead->id)->where('source', 'sla')->count())->toBe(1)
        ->and($lead->fresh()->sla_breached_at->equalTo($breachedAt))->toBeTrue();
});
