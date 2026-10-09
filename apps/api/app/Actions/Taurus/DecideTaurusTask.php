<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Enums\TaurusAgentStatus;
use App\Enums\TaurusTaskStatus;
use App\Models\TaurusAgent;
use App\Models\TaurusEvent;
use App\Models\TaurusTask;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A human approves or rejects a task a bot parked for approval (ADR 0052).
 * Approvals are human-only: nothing in the ingest path or the brain can make
 * this decision. The bot learns the outcome by polling the decisions feed.
 */
final class DecideTaurusTask
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws TaskNotAwaitingApproval when the task is not in needs_approval
     */
    public function handle(TaurusTask $task, bool $approve, ?string $note, User $actor): TaurusTask
    {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        $task = DB::transaction(function () use ($task, $approve, $note, $actor): TaurusTask {
            /** @var TaurusTask $locked */
            $locked = TaurusTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            // Re-checked under the lock so two admins clicking at once cannot
            // both decide the same request.
            if ($locked->status !== TaurusTaskStatus::NeedsApproval) {
                throw new TaskNotAwaitingApproval;
            }

            $now = now();

            $locked->fill([
                'status' => $approve ? TaurusTaskStatus::Running : TaurusTaskStatus::Rejected,
                'decision' => $approve ? 'approved' : 'rejected',
                'decision_note' => $note,
                'decided_by' => $actor->id,
                'decided_at' => $now,
                'finished_at' => $approve ? null : $now,
            ]);
            $locked->save();

            /** @var TaurusAgent $agent */
            $agent = $locked->agent()->firstOrFail();
            $agent->status = $approve ? TaurusAgentStatus::Working : TaurusAgentStatus::Idle;
            if ($approve) {
                $agent->current_task = Str::limit($locked->title, 237);
            } elseif ($agent->current_task === $locked->title) {
                $agent->current_task = null;
                $agent->progress = 0;
            }
            $agent->save();

            $action = $locked->approval_action ?: $locked->title;

            TaurusEvent::query()->create([
                'taurus_workspace_id' => $locked->taurus_workspace_id,
                'agent_id' => $agent->id,
                'task_id' => $locked->id,
                'kind' => TaurusEvent::KIND_APPROVAL,
                'status' => $locked->status->value,
                'message' => Str::limit(($approve ? 'approved: ' : 'rejected: ').$action, 497),
                'occurred_at' => $now,
            ]);

            return $locked;
        });

        $this->audit->log(
            $approve ? 'taurus.task.approved' : 'taurus.task.rejected',
            $task,
            ['ref' => $task->ref, 'note' => $note],
            $actor,
        );

        return $task;
    }
}
