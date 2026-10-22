<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Actions\Employers\AskHiringFloor;
use App\Actions\Taurus\BrainNotConfigured;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerJobStatus;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Employers\HiringChatAgent;
use App\Services\AI\AiBudgetExceeded;
use App\Support\AI\ProviderNotConfigured;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Agentic hiring: one recruiter turn on any channel — a WhatsApp message or
 * voice note, the hiring floor's Ask bar (typed or spoken), the Taurus page.
 *
 *   "go ahead with L1 for the Java role" → a proposal, waiting for YES
 *   "yes"                                → the proposal runs
 *   "no" / "not now"                     → the proposal is held
 *   "how many cleared L1?"               → an answer from the hiring floor
 *   "need 2 data engineers in Pune"      → post_job: the channel's JD flow
 *
 * Nothing runs without a YES. Owners and recruiters can ask Taurus to act;
 * a hiring manager can ask questions but is told who can act.
 */
final readonly class HiringAgent
{
    public function __construct(
        private HiringCommandReader $reader,
        private HiringActionPlanner $planner,
        private HiringActionExecutor $executor,
        private AskHiringFloor $ask,
        private WebJobPosting $posting,
        private InterviewReportLinks $reportLinks,
    ) {}

    public function respond(EmployerWorkspace $workspace, User $user, string $text, string $channel, ?EmployerJob $scope = null, ?EmployerWhatsappLink $link = null): AgentReply
    {
        $text = trim($text);
        $pending = $this->pending($workspace, $user, $link);

        if ($pending !== null && AgentWords::isYes($text)) {
            return $this->confirm($pending, $user);
        }
        if ($pending !== null && AgentWords::isNo($text)) {
            return $this->hold($pending, $user);
        }

        $read = $this->reader->read($user, $workspace, $text, $scope);
        if ($read === null) {
            return new AgentReply('unknown', 'I did not catch that. Tell me what you would like to do, for example "send the L1 invites for the Java role", or ask "where are we with hiring?".');
        }

        // On the web a new role is drafted right here; on WhatsApp the chat's own draft flow takes over.
        $draft = $channel === EmployerAgentAction::CHANNEL_WEB ? $this->openDraft($workspace, $user) : null;
        if ($channel === EmployerAgentAction::CHANNEL_WEB && ($read['intent'] === 'post_job' || ($draft !== null && $read['intent'] === 'other' && ! HiringChatAgent::isSmallTalk($text)))) {
            return $this->posting->handle($workspace, $user, $text, $draft);
        }

        return match ($read['intent']) {
            'post_job' => new AgentReply('post_job', ''),
            'question' => $this->answer($workspace, $user, $text, $this->job($workspace, $read['job_id']) ?? $scope),
            'act' => $this->act($workspace, $user, $read, $channel, $scope, $link),
            // Greetings and small talk are answered by Taurus with the floor in view, so "how are you?" gets a
            // real reply and one useful thing to look at, not a menu.
            default => $this->answer($workspace, $user, $text, $this->job($workspace, $read['job_id']) ?? $scope, $read['reply'] !== '' ? $read['reply'] : $this->help()),
        };
    }

    /** The role draft this person has waiting in the web Ask bar, if any. */
    private function openDraft(EmployerWorkspace $workspace, User $user): ?EmployerAgentAction
    {
        return EmployerAgentAction::query()->open()
            ->where('employer_workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('kind', EmployerAgentAction::KIND_POST_JOB)
            ->latest('id')
            ->first();
    }

    /** The proposal a bare YES on this channel would answer, newest first. */
    public function pending(EmployerWorkspace $workspace, User $user, ?EmployerWhatsappLink $link = null): ?EmployerAgentAction
    {
        return EmployerAgentAction::query()->open()
            ->where('employer_workspace_id', $workspace->id)
            ->where(function ($q) use ($user, $link): void {
                $q->where('user_id', $user->id)->orWhere('channel', EmployerAgentAction::CHANNEL_MILESTONE);
                if ($link !== null) {
                    $q->orWhere('employer_whatsapp_link_id', $link->id);
                }
            })
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function confirm(EmployerAgentAction $action, User $user): AgentReply
    {
        try {
            $result = $this->executor->confirm($action, $user);
        } catch (ValidationException $e) {
            return new AgentReply('failed', (string) (collect($e->errors())->flatten()->first() ?? $e->getMessage()), $action->fresh());
        }

        $action->refresh();

        return new AgentReply($action->status === EmployerAgentAction::DONE ? 'done' : 'failed', $result, $action);
    }

    public function hold(EmployerAgentAction $action, User $user): AgentReply
    {
        try {
            $this->executor->cancel($action, $user);
        } catch (ValidationException $e) {
            return new AgentReply('failed', (string) (collect($e->errors())->flatten()->first() ?? $e->getMessage()), $action);
        }

        return new AgentReply('held', 'Okay, I have held that. Nothing was sent. Tell me whenever you want to go ahead.', $action->fresh());
    }

    /** @param  array{intent: string, action: string|null, job_id: int|null, round: string|null, names: list<string>, top_n: int|null, from_stage: string|null, to_stage: string|null, reason: string|null, reply: string}  $read */
    private function act(EmployerWorkspace $workspace, User $user, array $read, string $channel, ?EmployerJob $scope, ?EmployerWhatsappLink $link): AgentReply
    {
        // Seeing a report changes nothing, so any member can ask for one and no YES is needed.
        if ($read['action'] === 'send_report') {
            $job = $this->job($workspace, $read['job_id']) ?? $scope ?? $this->onlyLiveJob($workspace) ?? $this->latestLiveJob($workspace, $link);

            return $job === null
                ? new AgentReply('chat', $this->whichRole($workspace))
                : new AgentReply('answered', $this->reportLinks->reply($job, $read['names'], $read['round'], $read['top_n']));
        }

        if (! HiringActionExecutor::canAct($workspace, $user)) {
            return new AgentReply('chat', 'Only a workspace owner or recruiter can ask me to do that. Ask one of them, or ask your owner for recruiter access.');
        }

        if ($read['action'] === null) {
            return new AgentReply('chat', 'I can send interview rounds, move candidates forward, reject, make offers, and publish, pause or close roles. What would you like me to do?');
        }

        $job = $this->job($workspace, $read['job_id']) ?? $scope ?? $this->onlyLiveJob($workspace);
        // Starting or moving people goes to the role posted last (from this chat, if it posted one):
        // two roles with the same title are not a reason to stop and ask. Changing a role's own status
        // (close, pause, publish) is never guessed.
        if ($job === null && ! in_array($read['action'], ['publish_job', 'pause_job', 'close_job'], true)) {
            $job = $this->latestLiveJob($workspace, $link);
        }
        if ($job === null) {
            return new AgentReply('chat', $this->whichRole($workspace));
        }

        $from = $read['from_stage'] !== null ? EmployerApplicationStage::from($read['from_stage']) : null;
        $plan = match ($read['action']) {
            'start_round' => $this->planner->startRound($job, $read['round'], $read['names'], $read['top_n']),
            'move_stage' => $read['to_stage'] === null
                ? 'Where should they go: the panel round, an offer, or hired?'
                : $this->planner->moveStage($job, EmployerApplicationStage::from($read['to_stage']), $read['names'], $from, $read['top_n']),
            'reject' => $this->planner->reject($job, $read['names'], $from, $read['top_n'], $read['reason']),
            'retake_round' => $this->planner->retakeByName($job, $read['round'], $read['names'], $from, $read['top_n']),
            'publish_job' => $this->planner->jobStatus($job, EmployerJobStatus::Published),
            'pause_job' => $this->planner->jobStatus($job, EmployerJobStatus::Paused),
            'close_job' => $this->planner->jobStatus($job, EmployerJobStatus::Closed),
            default => 'I cannot do that one yet.',
        };

        if (is_string($plan)) {
            return new AgentReply('chat', $plan);
        }

        $action = $this->planner->propose($plan, $workspace, $user, $channel, $link?->id);
        $ask = $channel === EmployerAgentAction::CHANNEL_WHATSAPP
            ? "\n\nReply *YES* to go ahead, or *NO* to hold."
            : ' Shall I go ahead?';

        return new AgentReply('proposed', $action->summary.$ask, $action);
    }

    private function answer(EmployerWorkspace $workspace, User $user, string $question, ?EmployerJob $job, ?string $fallback = null): AgentReply
    {
        try {
            return new AgentReply('answered', $this->ask->handle($user, $workspace, $job, $question)['answer']);
        } catch (BrainNotConfigured|ProviderNotConfigured) {
            return new AgentReply('chat', $fallback ?? 'I cannot answer questions about your pipeline just now. Your hiring floor shows every stage live.');
        } catch (AiBudgetExceeded) {
            return new AgentReply('chat', 'I have answered a lot today. Please try again tomorrow, or check the hiring floor.');
        } catch (Throwable $e) {
            report($e);

            return new AgentReply('chat', 'I could not answer that just now. Please try again in a minute.');
        }
    }

    private function job(EmployerWorkspace $workspace, ?int $id): ?EmployerJob
    {
        return $id === null ? null : EmployerJob::query()->where('employer_workspace_id', $workspace->id)->find($id);
    }

    /** With one live role there is nothing to ask. */
    private function onlyLiveJob(EmployerWorkspace $workspace): ?EmployerJob
    {
        $live = EmployerJob::query()
            ->where('employer_workspace_id', $workspace->id)
            ->where('status', EmployerJobStatus::Published->value)
            ->limit(2)
            ->get();

        return $live->count() === 1 ? $live->first() : null;
    }

    /** The live role posted from this chat last, else the most recently posted live role. */
    private function latestLiveJob(EmployerWorkspace $workspace, ?EmployerWhatsappLink $link): ?EmployerJob
    {
        return EmployerJob::query()
            ->where('employer_workspace_id', $workspace->id)
            ->where('status', EmployerJobStatus::Published->value)
            ->orderByRaw('CASE WHEN whatsapp_link_id = ? THEN 0 ELSE 1 END', [$link?->id ?? 0])
            ->latest('id')
            ->first();
    }

    private function whichRole(EmployerWorkspace $workspace): string
    {
        $jobs = EmployerJob::query()
            ->where('employer_workspace_id', $workspace->id)
            ->where('status', '!=', EmployerJobStatus::Closed->value)
            ->latest('id')
            ->limit(6)
            ->get(['id', 'title', 'created_at']);

        // The date and number tell two roles with the same title apart.
        return $jobs->isEmpty()
            ? 'You have no roles yet. Tell me about one and I will post it.'
            : 'Which role? '.$jobs->map(fn (EmployerJob $j) => "*{$j->title}* (#{$j->id}, posted {$j->created_at?->format('j M')})")->implode(', ').'.';
    }

    private function help(): string
    {
        return 'I can run your hiring for you once you say yes: "send the L1 invites for the Java role", "invite everyone who cleared L1 to L2", "make an offer to Priya", "pause the Data Engineer role". Or ask me "where are we with hiring?".';
    }
}
