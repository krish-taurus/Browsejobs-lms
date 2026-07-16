<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\ContactTimelineEvent;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Creates a counselor follow-up task against a lead (PRD §6.12 task queue).
 */
final readonly class CreateTask
{
    public function __construct(private RecordTimelineEvent $timeline) {}

    public function handle(Lead $lead, User $assignee, string $title, ?Carbon $dueAt = null, ?User $actor = null, string $source = CrmTask::SOURCE_MANUAL): CrmTask
    {
        $task = CrmTask::query()->create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'assigned_to' => $assignee->id,
            'title' => $title,
            'due_at' => $dueAt,
            'source' => $source,
        ]);

        $this->timeline->handle(
            $lead,
            ContactTimelineEvent::TYPE_TASK,
            "Task created: {$title}",
            ['task_id' => $task->id, 'assignee_id' => $assignee->id],
            $actor,
        );

        return $task;
    }
}
