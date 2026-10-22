<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Enums\EmployerJobStatus;
use App\Models\EmployerAgentAction;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\JobShortlistEntry;
use App\Models\User;
use App\Support\Employers\InterviewProcess;
use App\Support\Employers\InterviewTemplates;
use App\Support\Employers\ShortlistSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns "send L1 for the Java role" into an exact proposal: the rows it will
 * touch and one sentence the client can say YES to. Nothing here changes the
 * pipeline — HiringActionExecutor does that, only after the YES.
 *
 * Every method returns a PlannedAction, or a plain sentence saying why there
 * is nothing to propose ("Everyone shortlisted has already been invited").
 */
final readonly class HiringActionPlanner
{
    /** Proposals made in a conversation are answered there and then. */
    public const CHAT_HOURS = 24;

    /** Milestone proposals wait for the client a little longer. */
    public const MILESTONE_HOURS = 72;

    private const NAMES_SHOWN = 5;

    public function __construct(
        private InterviewProcess $process,
        private InterviewTemplates $templates,
    ) {}

    /**
     * @param  list<string>  $names
     */
    public function startRound(EmployerJob $job, ?string $roundKey, array $names = [], ?int $topN = null): PlannedAction|string
    {
        $rounds = $this->process->forJob($job)->filter(fn ($r) => $r->enabled)->values();
        $first = $rounds->first();
        $round = $roundKey === null ? $first : $rounds->firstWhere('key', $roundKey);
        if ($round === null) {
            return $roundKey === null
                ? "*{$job->title}* has no interview rounds set up yet."
                : '*'.$job->title.'* has no round called '.strtoupper($roundKey).'. Its rounds are '.$rounds->pluck('key')->map(fn ($k) => strtoupper($k))->implode(', ').'.';
        }
        if (! $round->isPlatformRun()) {
            return strtoupper($round->key)." for *{$job->title}* is a human round — your interviewer runs it, so there is no AI invite to send.";
        }

        return $round->is($first)
            ? $this->firstRound($job, $round->key, $names, $topN)
            : $this->laterRound($job, $round->key, (string) $rounds->get((int) $rounds->search(fn ($r) => $r->is($round)) - 1)?->key, $names, $topN);
    }

    /**
     * Everyone on the AI shortlist not yet invited: they each get the WhatsApp
     * to pick a day (SendInterviewInvites).
     *
     * @param  list<string>  $names
     */
    private function firstRound(EmployerJob $job, string $key, array $names, ?int $topN): PlannedAction|string
    {
        if ($key !== 'l1') {
            return 'The first round of *'.$job->title.'* is '.strtoupper($key).', which is sent from the pipeline rather than as a WhatsApp invite.';
        }
        if (! $this->templates->approved(InterviewTemplates::INVITE)) {
            return 'I cannot send L1 invites yet: the WhatsApp interview-invite message is still waiting for Meta to approve it. It shows on the AI shortlist page.';
        }

        $entries = JobShortlistEntry::query()
            ->where('employer_job_id', $job->id)
            ->where('status', JobShortlistEntry::STATUS_SHORTLISTED)
            ->with(['employerCandidate', 'candidateUser:id,name'])
            ->orderBy('rank')
            ->get();
        if ($entries->isEmpty()) {
            return "Nobody is on the shortlist for *{$job->title}* yet, so there is no one to invite to L1.";
        }

        $invited = InterviewInvite::query()
            ->where('employer_job_id', $job->id)
            ->where('round', 'l1')
            ->where('status', '!=', InterviewInvite::FAILED)
            ->pluck('candidate_user_id')
            ->all();
        $fresh = $entries->reject(function (JobShortlistEntry $e) use ($invited): bool {
            $userId = $e->candidate_user_id ?? $e->employerCandidate?->user_id;

            return $userId !== null && in_array($userId, $invited, true);
        })->values();
        if ($fresh->isEmpty()) {
            return "Everyone shortlisted for *{$job->title}* has already been invited to L1.";
        }

        $nameOf = fn (JobShortlistEntry $e): string => (string) ($e->employerCandidate?->name ?? $e->candidateUser?->name ?? 'Candidate');
        $picked = $this->pick($fresh, $nameOf, $names, $topN);
        if (is_string($picked)) {
            return $picked;
        }

        return new PlannedAction(
            EmployerAgentAction::KIND_START_ROUND,
            $job->id,
            ['round' => $key, 'entry_ids' => $picked->pluck('id')->values()->all()],
            'Send L1 interview invites to '.$this->people($picked->map($nameOf))." for *{$job->title}*. Each gets a WhatsApp to pick a day; the interview link goes out on that day.",
        );
    }

    /**
     * Everyone who cleared the round before at the pass mark and has not been
     * asked for this one (InviteToNextRound).
     *
     * @param  list<string>  $names
     */
    private function laterRound(EmployerJob $job, string $key, string $previous, array $names, ?int $topN): PlannedAction|string
    {
        if (! $this->templates->approved(InterviewTemplates::NEXT_ROUND)) {
            return 'I cannot send '.strtoupper($key).' invites yet: the WhatsApp next-round message is still waiting for Meta to approve it.';
        }

        $passMark = ShortlistSettings::forJob($job)->roundPassMark;
        $graded = EmployerInterview::query()
            ->whereHas('application', fn ($q) => $q->where('employer_job_id', $job->id))
            ->where('round', $previous)
            ->where('status', EmployerInterviewStatus::Graded->value)
            ->with('application.candidate:id,name')
            ->orderByDesc('overall_score')
            ->get()
            ->unique('employer_job_application_id')
            ->filter(fn (EmployerInterview $i) => $i->application !== null && ! $i->application->stage->isTerminal())
            ->values();
        $cleared = $graded->filter(fn (EmployerInterview $i) => (int) $i->overall_score >= $passMark)->values();
        $below = $graded->reject(fn (EmployerInterview $i) => (int) $i->overall_score >= $passMark)->values();
        $prev = strtoupper($previous);

        // The pass mark is the client's own rule, so the client may overrule it for
        // someone they name ("let's process Kunal to L2"). The YES still comes first.
        $override = $names !== [];
        $passed = $override ? $graded : $cleared;
        if ($passed->isEmpty()) {
            if ($below->isNotEmpty()) {
                $nameBelow = fn (EmployerInterview $i): string => ($i->application?->candidate?->name ?? 'Candidate')." ({$i->overall_score})";

                return "Nobody has cleared {$prev} for *{$job->title}* yet (pass mark {$passMark}). Below it: ".$this->people($below->map($nameBelow))
                    .'. To move someone ahead anyway, say their name, for example "move '.Str::before((string) ($below->first()->application?->candidate?->name ?? 'Kunal'), ' ').' to '.strtoupper($key).'".';
            }

            return "Nobody has cleared {$prev} for *{$job->title}* yet (pass mark {$passMark}).";
        }

        $asked = InterviewInvite::query()
            ->where('employer_job_id', $job->id)
            ->where('round', $key)
            ->pluck('employer_job_application_id')
            ->all();
        $fresh = $passed->reject(fn (EmployerInterview $i) => in_array($i->employer_job_application_id, $asked, true))->values();
        if ($fresh->isEmpty()) {
            return "Everyone who cleared {$prev} for *{$job->title}* has already been invited to ".strtoupper($key).'.';
        }

        $nameOf = fn (EmployerInterview $i): string => (string) ($i->application?->candidate?->name ?? 'Candidate');
        $picked = $this->pick($fresh, $nameOf, $names, $topN);
        if (is_string($picked)) {
            return $picked;
        }

        return new PlannedAction(
            EmployerAgentAction::KIND_START_ROUND,
            $job->id,
            ['round' => $key, 'application_ids' => $picked->pluck('employer_job_application_id')->values()->all()],
            $this->laterRoundSummary($picked, $nameOf, $job, $prev, $key, $passMark),
        );
    }

    /**
     * One sentence for the YES: who, and plainly which of them are below the
     * pass mark and moved ahead on the client's say-so.
     *
     * @param  Collection<int, EmployerInterview>  $picked
     */
    private function laterRoundSummary(Collection $picked, callable $nameOf, EmployerJob $job, string $prev, string $key, int $passMark): string
    {
        $label = fn (EmployerInterview $i): string => $nameOf($i)." ({$i->overall_score})";
        [$over, $ok] = [
            $picked->filter(fn (EmployerInterview $i) => (int) $i->overall_score < $passMark),
            $picked->filter(fn (EmployerInterview $i) => (int) $i->overall_score >= $passMark),
        ];
        $to = strtoupper($key).' AI interview. Each picks a day on WhatsApp.';
        if ($over->isEmpty()) {
            return 'Invite '.$this->people($picked->map($label))." who cleared {$prev} for *{$job->title}* to the {$to}";
        }
        if ($ok->isEmpty()) {
            return 'Invite '.$this->people($over->map($label))." to the {$to} They scored below the {$passMark} pass mark for {$prev}; I am moving them ahead because you asked.";
        }

        return 'Invite '.$this->people($picked->map($label))." to the {$to} ".$this->people($over->map($label)).' scored below the '.$passMark.' pass mark; I am moving them ahead because you asked.';
    }

    /**
     * Forward moves a human decides: human round, offer, hired. A move "to
     * L1/L2" is a round invite and goes through startRound instead.
     *
     * @param  list<string>  $names
     */
    public function moveStage(EmployerJob $job, EmployerApplicationStage $to, array $names, ?EmployerApplicationStage $from, ?int $topN): PlannedAction|string
    {
        $apps = $this->applications($job, $names, $from, $topN);
        if (is_string($apps)) {
            return $apps;
        }

        $movable = $apps->filter(fn (EmployerJobApplication $a) => $a->stage !== $to && $a->stage->canAdvanceTo($to))->values();
        if ($movable->isEmpty()) {
            return 'None of them can move to '.$this->stageLabel($to).' from where they are now.';
        }

        $verb = match ($to) {
            EmployerApplicationStage::Offer => 'Move to *Offer*:',
            EmployerApplicationStage::Hired => 'Mark as *Hired*:',
            default => 'Move to the *'.$this->stageLabel($to).'*:',
        };

        return new PlannedAction(
            EmployerAgentAction::KIND_MOVE_STAGE,
            $job->id,
            ['stage' => $to->value, 'application_ids' => $movable->pluck('id')->all()],
            "{$verb} ".$this->people($movable->map(fn ($a) => (string) ($a->candidate?->name ?? 'Candidate')))." for *{$job->title}*.",
        );
    }

    /** @param  list<string>  $names */
    public function reject(EmployerJob $job, array $names, ?EmployerApplicationStage $from, ?int $topN, ?string $reason): PlannedAction|string
    {
        if ($reason === null || trim($reason) === '') {
            return 'What reason should I record for the rejection? It is kept on their application. Say it again with the reason, for example "reject Rahul, not enough Kafka experience".';
        }

        $apps = $this->applications($job, $names, $from, $topN);
        if (is_string($apps)) {
            return $apps;
        }

        return new PlannedAction(
            EmployerAgentAction::KIND_REJECT,
            $job->id,
            ['reason' => Str::limit(trim($reason), 300, ''), 'application_ids' => $apps->pluck('id')->all()],
            'Reject '.$this->people($apps->map(fn ($a) => (string) ($a->candidate?->name ?? 'Candidate')))." for *{$job->title}*. Reason recorded: \"".Str::limit(trim($reason), 120).'"',
        );
    }

    /**
     * Let the chosen candidates sit a round again. Only people who were invited to it
     * can retake it; the first result stays on record.
     *
     * @param  list<int>  $applicationIds
     */
    public function retakeRound(EmployerJob $job, string $roundKey, array $applicationIds): PlannedAction|string
    {
        $invites = InterviewInvite::query()
            ->where('employer_job_id', $job->id)
            ->where('round', $roundKey)
            ->whereIn('employer_job_application_id', $applicationIds)
            ->with(['candidate:id,name', 'application', 'interview'])
            ->get()
            ->filter(fn (InterviewInvite $i) => $i->application !== null && ! $i->application->stage->isTerminal())
            ->values();

        if ($invites->isEmpty()) {
            return 'None of them can retake '.strtoupper($roundKey).' for *'.$job->title.'*: they were never invited to it, or they are already hired or rejected.';
        }

        $label = fn (InterviewInvite $i): string => (string) ($i->candidate?->name ?? 'Candidate')
            .($i->interview?->overall_score !== null ? " ({$i->interview->overall_score}/100 before)" : '');

        return new PlannedAction(
            EmployerAgentAction::KIND_RETAKE_ROUND,
            $job->id,
            ['round' => $roundKey, 'application_ids' => $invites->pluck('employer_job_application_id')->values()->all()],
            'Let '.$this->people($invites->map($label)).' retake '.strtoupper($roundKey)." for *{$job->title}*. Each gets a new WhatsApp to pick a day; the first result stays on record.",
        );
    }

    /**
     * "Let Kunal retake L1": the people are named (or a stage is said), the round defaults to L1.
     *
     * @param  list<string>  $names
     */
    public function retakeByName(EmployerJob $job, ?string $roundKey, array $names, ?EmployerApplicationStage $from, ?int $topN): PlannedAction|string
    {
        $apps = $this->applications($job, $names, $from, $topN);
        if (is_string($apps)) {
            return $apps;
        }

        return $this->retakeRound($job, $roundKey ?? 'l1', $apps->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function jobStatus(EmployerJob $job, EmployerJobStatus $to): PlannedAction|string
    {
        if ($job->status === $to) {
            return "*{$job->title}* is already {$to->value}.";
        }
        if (! $job->status->canTransitionTo($to)) {
            return "*{$job->title}* is {$job->status->value} and cannot be {$to->value} from there.";
        }

        $summary = match ($to) {
            EmployerJobStatus::Published => "Publish *{$job->title}*. It goes live on the job board and the shortlist agent starts looking.",
            EmployerJobStatus::Paused => "Pause *{$job->title}*. It leaves the job board; candidates already in the pipeline stay.",
            EmployerJobStatus::Closed => "Close *{$job->title}*. It leaves the job board for good; this cannot be undone.",
            default => "Change *{$job->title}* to {$to->value}.",
        };

        return new PlannedAction(EmployerAgentAction::KIND_JOB_STATUS, $job->id, ['status' => $to->value], $summary);
    }

    /**
     * Save a proposal. A milestone proposal (with a topic) replaces the open
     * one for the same job and step rather than stacking a second question.
     */
    public function propose(PlannedAction $plan, EmployerWorkspace $workspace, ?User $user, string $channel, ?int $linkId = null, ?string $topic = null): EmployerAgentAction
    {
        $hours = $channel === EmployerAgentAction::CHANNEL_MILESTONE ? self::MILESTONE_HOURS : self::CHAT_HOURS;

        $open = $topic !== null
            ? EmployerAgentAction::query()->open()->where('employer_job_id', $plan->jobId)->where('topic', $topic)->latest('id')->first()
            : null;

        $fields = [
            'tenant_id' => $workspace->tenant_id,
            'employer_workspace_id' => $workspace->id,
            'employer_job_id' => $plan->jobId,
            'user_id' => $user?->id,
            'employer_whatsapp_link_id' => $linkId ?? $open?->employer_whatsapp_link_id,
            'channel' => $channel,
            'kind' => $plan->kind,
            'topic' => $topic,
            'params' => $plan->params,
            'summary' => $plan->summary,
            'status' => EmployerAgentAction::PROPOSED,
            'expires_at' => now()->addHours($hours),
        ];

        if ($open !== null) {
            $open->update($fields);

            return $open;
        }

        // A new question in a chat supersedes the one before it there: a YES
        // must never land on an older proposal the client has moved past.
        if ($topic === null && $user !== null) {
            EmployerAgentAction::query()->open()
                ->where('employer_workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->whereNull('topic')
                ->update(['status' => EmployerAgentAction::CANCELLED, 'result' => 'Replaced by a newer request.']);
        }

        return EmployerAgentAction::query()->create($fields);
    }

    /**
     * Applications for named people, or a whole stage, optionally the top N by
     * their latest interview score.
     *
     * @param  list<string>  $names
     * @return Collection<int, EmployerJobApplication>|string
     */
    private function applications(EmployerJob $job, array $names, ?EmployerApplicationStage $from, ?int $topN): Collection|string
    {
        if ($names === [] && $from === null) {
            return "Who for *{$job->title}*? Name them, or say a stage (for example \"everyone in the panel round\").";
        }

        $apps = EmployerJobApplication::query()
            ->where('employer_job_id', $job->id)
            ->when($from !== null, fn ($q) => $q->where('stage', $from->value))
            ->whereNotIn('stage', [EmployerApplicationStage::Hired->value, EmployerApplicationStage::Rejected->value, EmployerApplicationStage::Withdrawn->value])
            ->with('candidate:id,name')
            ->get();
        if ($apps->isEmpty()) {
            return $from !== null
                ? 'Nobody is in '.$this->stageLabel($from)." for *{$job->title}* right now."
                : "There is nobody in the pipeline for *{$job->title}* yet.";
        }

        if ($topN !== null) {
            $scores = EmployerInterview::query()
                ->whereIn('employer_job_application_id', $apps->pluck('id'))
                ->whereNotNull('overall_score')
                ->selectRaw('employer_job_application_id, max(overall_score) as best')
                ->groupBy('employer_job_application_id')
                ->pluck('best', 'employer_job_application_id');
            $apps = $apps->sortByDesc(fn ($a) => (int) ($scores[$a->id] ?? -1))->values();
        }

        return $this->pick($apps, fn (EmployerJobApplication $a): string => (string) ($a->candidate?->name ?? ''), $names, $topN);
    }

    /**
     * Narrow a list to the people named ("Priya" finds "Priya Sharma"), or
     * the first N. A name that fits nobody, or two people, is asked about —
     * never guessed.
     *
     * @template T
     *
     * @param  Collection<int, T>  $rows
     * @param  callable(T): string  $nameOf
     * @param  list<string>  $names
     * @return Collection<int, T>|string
     */
    private function pick(Collection $rows, callable $nameOf, array $names, ?int $topN): Collection|string
    {
        if ($names !== []) {
            $chosen = collect();
            foreach ($names as $said) {
                $matches = $rows->filter(fn ($row) => self::nameMatches($said, $nameOf($row)));
                if ($matches->isEmpty()) {
                    return "I could not find anyone called \"{$said}\" there. The people I can see are: ".$this->people($rows->map($nameOf), 10).'.';
                }
                if ($matches->count() > 1) {
                    return "More than one person matches \"{$said}\": ".$matches->map($nameOf)->implode(', ').'. Which one? Say the full name.';
                }
                $chosen->push($matches->first());
            }
            $rows = $chosen->unique(fn ($row) => spl_object_id($row))->values();
        }

        return $topN !== null ? $rows->take($topN)->values() : $rows->values();
    }

    /** Every word said starts a word of the real name, in any order. */
    public static function nameMatches(string $said, string $actual): bool
    {
        $words = fn (string $s): array => array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)) ?: []));
        $want = $words($said);
        $have = $words($actual);
        if ($want === [] || $have === []) {
            return false;
        }

        foreach ($want as $w) {
            if (! collect($have)->contains(fn (string $h) => str_starts_with($h, $w))) {
                return false;
            }
        }

        return true;
    }

    /** @param  Collection<int, string>  $names "3 people (Priya, Rahul, Asha)" */
    private function people(Collection $names, int $show = self::NAMES_SHOWN): string
    {
        $n = $names->count();
        $list = $names->take($show)->implode(', ');
        $more = $n > $show ? ' +'.($n - $show).' more' : '';

        return $n === 1 ? (string) $names->first() : "{$n} people ({$list}{$more})";
    }

    private function stageLabel(EmployerApplicationStage $stage): string
    {
        return match ($stage) {
            EmployerApplicationStage::HumanRound => 'panel round',
            EmployerApplicationStage::L1, EmployerApplicationStage::L2 => strtoupper($stage->value),
            default => Str::headline($stage->value),
        };
    }
}
