<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Actions\Employers\ChangeEmployerJobStatus;
use App\Actions\Employers\InviteToNextRound;
use App\Actions\Employers\MoveApplicationStage;
use App\Actions\Employers\PublishEmployerJob;
use App\Actions\Employers\SendInterviewInvites;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerJobStatus;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Carries out a proposal the client said YES to — the only place agentic
 * hiring changes anything, and only through the same actions the pipeline
 * buttons use (so their own guards, idempotency and audit all still apply).
 *
 * The YES is checked again here, whatever channel it came from: the person
 * must still be an owner or recruiter of an active workspace, and the
 * proposal must still be open. Claiming it is a single guarded UPDATE, so a
 * double tap or a YES on two channels runs it once.
 */
final readonly class HiringActionExecutor
{
    public function __construct(
        private SendInterviewInvites $l1,
        private InviteToNextRound $nextRound,
        private MoveApplicationStage $move,
        private PublishEmployerJob $publish,
        private ChangeEmployerJobStatus $status,
        private AuditLogger $audit,
    ) {}

    /**
     * What happened, in one or two sentences for the client.
     *
     * @throws ValidationException when this person may not act, or it is no longer open
     */
    public function confirm(EmployerAgentAction $action, User $actor): string
    {
        $workspace = EmployerWorkspace::query()->find($action->employer_workspace_id);
        $this->authorize($workspace, $actor);

        $claimed = EmployerAgentAction::query()
            ->whereKey($action->id)
            ->where('status', EmployerAgentAction::PROPOSED)
            ->where('expires_at', '>', now())
            ->update(['status' => EmployerAgentAction::RUNNING, 'decided_by' => $actor->id, 'decided_at' => now()]);
        if ($claimed !== 1) {
            $action->refresh();
            throw ValidationException::withMessages(['action' => match ($action->status) {
                EmployerAgentAction::DONE, EmployerAgentAction::RUNNING => 'That has already been done.',
                EmployerAgentAction::CANCELLED => 'That was cancelled, so nothing was sent.',
                default => 'That request has expired. Ask me again and I will set it up fresh.',
            }]);
        }

        $job = EmployerJob::query()->find($action->employer_job_id);

        try {
            $result = $job === null ? 'That role no longer exists, so nothing was done.' : $this->run($action, $job, $actor);
            $status = EmployerAgentAction::DONE;
        } catch (ValidationException $e) {
            $result = 'I could not do that: '.(string) (collect($e->errors())->flatten()->first() ?? $e->getMessage());
            $status = EmployerAgentAction::FAILED;
        } catch (Throwable $e) {
            report($e);
            $result = 'Something went wrong while doing that. Nothing more will be sent; please try again in a minute.';
            $status = EmployerAgentAction::FAILED;
        }

        $action->update(['status' => $status, 'result' => $result]);
        $this->audit->log('employer.agent_action_'.($status === EmployerAgentAction::DONE ? 'done' : 'failed'), $action, [
            'kind' => $action->kind,
            'channel' => $action->channel,
            'job_id' => $action->employer_job_id,
        ], $actor);

        return $result;
    }

    public function cancel(EmployerAgentAction $action, User $actor): void
    {
        $workspace = EmployerWorkspace::query()->find($action->employer_workspace_id);
        $this->authorize($workspace, $actor);

        $cancelled = EmployerAgentAction::query()
            ->whereKey($action->id)
            ->where('status', EmployerAgentAction::PROPOSED)
            ->update(['status' => EmployerAgentAction::CANCELLED, 'decided_by' => $actor->id, 'decided_at' => now(), 'result' => 'Held. Nothing was sent.']);

        if ($cancelled === 1) {
            $this->audit->log('employer.agent_action_cancelled', $action, ['kind' => $action->kind], $actor);
        }
    }

    public static function canAct(?EmployerWorkspace $workspace, User $user): bool
    {
        $member = $workspace?->memberFor($user);

        return $member !== null
            && $member->role->managesPipeline()
            && $workspace->status !== EmployerWorkspace::STATUS_SUSPENDED;
    }

    private function authorize(?EmployerWorkspace $workspace, User $actor): void
    {
        if (! self::canAct($workspace, $actor)) {
            throw ValidationException::withMessages([
                'action' => 'Only a workspace owner or recruiter can approve that.',
            ]);
        }
    }

    private function run(EmployerAgentAction $action, EmployerJob $job, User $actor): string
    {
        return app(TenantContext::class)->run($job->tenant, function () use ($action, $job, $actor): string {
            $p = $action->params;

            return match ($action->kind) {
                EmployerAgentAction::KIND_START_ROUND => isset($p['entry_ids'])
                    ? $this->firstRound($job, $actor, array_map('intval', (array) $p['entry_ids']))
                    : $this->laterRound($job, $actor, (string) $p['round'], array_map('intval', (array) ($p['application_ids'] ?? []))),
                EmployerAgentAction::KIND_MOVE_STAGE => $this->moveAll($job, $actor, EmployerApplicationStage::from((string) $p['stage']), array_map('intval', (array) $p['application_ids']), null),
                EmployerAgentAction::KIND_REJECT => $this->moveAll($job, $actor, EmployerApplicationStage::Rejected, array_map('intval', (array) $p['application_ids']), (string) $p['reason']),
                EmployerAgentAction::KIND_JOB_STATUS => $this->jobStatus($job, $actor, EmployerJobStatus::from((string) $p['status'])),
                default => throw ValidationException::withMessages(['action' => 'I do not know how to do that.']),
            };
        });
    }

    /** @param  list<int>  $entryIds */
    private function firstRound(EmployerJob $job, User $actor, array $entryIds): string
    {
        $sent = $this->l1->handle($job, $actor, $entryIds);
        $skipped = collect($sent['skipped'])->map(fn (int $n, string $why) => "{$n} {$why}")->implode(', ');

        if ($sent['queued'] === 0) {
            return "No L1 invites went out for *{$job->title}*".($skipped !== '' ? " ({$skipped})." : '.');
        }

        return "✅ Sent L1 invites to {$sent['queued']} ".Str::plural('candidate', $sent['queued'])." for *{$job->title}*"
            .($skipped !== '' ? " (skipped: {$skipped})" : '')
            .'. They pick a day on WhatsApp; I will tell you as they book and as scores come in.';
    }

    /** @param  list<int>  $applicationIds */
    private function laterRound(EmployerJob $job, User $actor, string $round, array $applicationIds): string
    {
        $sent = 0;
        foreach ($this->applications($job, $applicationIds) as $application) {
            if (! $application->stage->isTerminal() && $this->nextRound->handle($job, $application, $round, $actor) !== null) {
                $sent++;
            }
        }
        $label = strtoupper($round);
        $left = count($applicationIds) - $sent;

        return $sent === 0
            ? "No {$label} invites went out for *{$job->title}*: they had already been asked, or have no WhatsApp number."
            : "✅ Asked {$sent} ".Str::plural('candidate', $sent)." to book the {$label} interview for *{$job->title}*"
                .($left > 0 ? " ({$left} already asked or unreachable)" : '').'.';
    }

    /** @param  list<int>  $applicationIds */
    private function moveAll(EmployerJob $job, User $actor, EmployerApplicationStage $to, array $applicationIds, ?string $note): string
    {
        $moved = [];
        $failed = 0;
        foreach ($this->applications($job, $applicationIds) as $application) {
            try {
                $this->move->handle($application, $to, $actor, $note ?? 'Approved with Taurus');
                $moved[] = (string) ($application->candidate()->withoutGlobalScopes()->value('name') ?? 'Candidate');
            } catch (ValidationException) {
                // Moved on by someone else since the proposal; skip them, do not undo anyone.
                $failed++;
            }
        }

        $where = match ($to) {
            EmployerApplicationStage::Rejected => 'Rejected',
            EmployerApplicationStage::Offer => 'Moved to Offer',
            EmployerApplicationStage::Hired => 'Marked hired',
            EmployerApplicationStage::HumanRound => 'Moved to the panel round',
            default => 'Moved to '.Str::headline($to->value),
        };

        if ($moved === []) {
            return "Nobody was moved for *{$job->title}*: they are no longer where they were.";
        }

        return "✅ {$where}: ".implode(', ', $moved)." (*{$job->title}*)".($failed > 0 ? ". {$failed} had already moved on, so I left them." : '.');
    }

    private function jobStatus(EmployerJob $job, User $actor, EmployerJobStatus $to): string
    {
        if ($to === EmployerJobStatus::Published) {
            $this->publish->handle($job, $actor);

            return "✅ *{$job->title}* is live. The shortlist agent is looking for candidates now.";
        }

        $this->status->handle($job, $to, $actor);

        return "✅ *{$job->title}* is {$to->value}.";
    }

    /**
     * Only the rows the client was shown, and only if they still belong to this role.
     *
     * @param  list<int>  $ids
     * @return Collection<int, EmployerJobApplication>
     */
    private function applications(EmployerJob $job, array $ids): Collection
    {
        return EmployerJobApplication::query()
            ->where('employer_job_id', $job->id)
            ->whereIn('id', $ids)
            ->get();
    }
}
