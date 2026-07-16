<?php

declare(strict_types=1);

use App\Actions\Crm\CompleteTask;
use App\Actions\Crm\CreateTask;
use App\Models\ContactTimelineEvent;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->assignee = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->lead = Lead::factory()->for($this->tenant)->create();
});

it('creates a task and records it on the timeline', function () {
    $task = withinTenant($this->tenant, fn () => app(CreateTask::class)->handle(
        $this->lead, $this->assignee, 'Call back tomorrow', now()->addDay(),
    ));

    expect($task->completed_at)->toBeNull()
        ->and($task->assigned_to)->toBe($this->assignee->id)
        ->and(ContactTimelineEvent::withoutGlobalScopes()
            ->where('lead_id', $this->lead->id)->where('type', ContactTimelineEvent::TYPE_TASK)->count())->toBe(1);
});

it('completes a task idempotently', function () {
    $task = CrmTask::factory()->for($this->tenant)->create([
        'lead_id' => $this->lead->id, 'assigned_to' => $this->assignee->id,
    ]);

    $done = app(CompleteTask::class)->handle($task);
    $firstCompletedAt = $done->completed_at;
    app(CompleteTask::class)->handle($task->fresh());

    expect($firstCompletedAt)->not->toBeNull()
        ->and($task->fresh()->completed_at->equalTo($firstCompletedAt))->toBeTrue();
});
