<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Jobs\SendClientUpdate;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWorkspace;
use App\Support\Employers\Agent\HiringActionPlanner;
use App\Support\Employers\Agent\PlannedAction;
use App\Support\Tenancy\TenantContext;

/**
 * Taurus speaks first (agentic hiring): at a milestone of a JD whose client
 * approves each round, propose the next step instead of taking it.
 *
 *   shortlist ready   → "Send L1 invites to these 8? Reply YES"
 *   someone clears L1 → "Invite the 3 who cleared L1 to L2? Reply YES"
 *
 * One open proposal per job and step: as more people clear a round, the same
 * proposal grows rather than a new question arriving for each of them, and
 * the client is re-asked at most every REASK_MINUTES. The question goes to the
 * JD's WhatsApp chat (or the workspace's latest one) and shows on the hiring
 * floor and the Taurus page with Confirm / Not now.
 */
final readonly class ProposeHiringStep
{
    public const REASK_MINUTES = 30;

    public function __construct(private HiringActionPlanner $planner) {}

    public function firstRound(EmployerJob $job): ?EmployerAgentAction
    {
        return $this->propose($job, 'round:l1', $this->planner->startRound($job, 'l1'));
    }

    public function nextRound(EmployerJob $job, string $roundKey): ?EmployerAgentAction
    {
        return $this->propose($job, "round:{$roundKey}", $this->planner->startRound($job, $roundKey));
    }

    private function propose(EmployerJob $job, string $topic, PlannedAction|string $plan): ?EmployerAgentAction
    {
        if (! $plan instanceof PlannedAction) {
            return null; // Nothing to ask about (nobody left, or the message isn't approved yet).
        }

        return app(TenantContext::class)->run($job->tenant, function () use ($job, $topic, $plan): ?EmployerAgentAction {
            $workspace = EmployerWorkspace::query()->find($job->employer_workspace_id);
            if ($workspace === null) {
                return null;
            }

            $link = ($job->whatsapp_link_id !== null ? EmployerWhatsappLink::query()->find($job->whatsapp_link_id) : null)
                ?? EmployerWhatsappLink::query()->where('employer_workspace_id', $workspace->id)->latest('last_inbound_at')->first();

            $before = EmployerAgentAction::query()->open()->where('employer_job_id', $job->id)->where('topic', $topic)->first();
            $action = $this->planner->propose($plan, $workspace, null, EmployerAgentAction::CHANNEL_MILESTONE, $link?->id, $topic);

            $grew = $before === null || $before->summary !== $action->summary;
            $quiet = $action->notified_at !== null && $action->notified_at->gt(now()->subMinutes(self::REASK_MINUTES));
            if ($link !== null && $grew && ! $quiet) {
                $action->update(['notified_at' => now()]);
                SendClientUpdate::dispatch($job->id, "🤖 {$action->summary} Shall I go ahead? Reply YES to send, or NO to hold.");
            }

            return $action;
        });
    }
}
