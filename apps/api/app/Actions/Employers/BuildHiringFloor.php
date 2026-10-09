<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Enums\EmployerJobStatus;
use App\Models\ApplicationStageTransition;
use App\Models\EmployerAutomationRun;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Support\Taurus\TaurusClock;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The employer's "hiring floor" (ADR 0052): the Taurus recruitment floor drawn
 * from the employer's real pipeline. Every number comes from existing
 * pipeline tables — applications, stage transitions, interviews, automation
 * runs. Nothing is simulated, and no candidate is ever named: the feed speaks
 * in job titles only.
 */
final class BuildHiringFloor
{
    private const EVENT_LIMIT = 30;

    private const IN_FLIGHT = ['invited', 'in_progress', 'submitted'];

    private const LABELS = [
        'applied' => 'Applied',
        'graded' => 'Screened',
        'shortlisted' => 'Shortlisted',
        'l1' => 'L1 interview',
        'l2' => 'L2 interview',
        'human_round' => 'Human round',
        'offer' => 'Offer',
        'hired' => 'Hired',
    ];

    private CarbonInterface $now;

    private CarbonInterface $today;

    private CarbonInterface $dayAgo;

    /**
     * @return array<string, mixed>
     */
    public function handle(EmployerWorkspace $workspace, ?EmployerJob $job = null): array
    {
        $this->now = now();
        $this->today = TaurusClock::startOfToday();
        $this->dayAgo = $this->now->copy()->subDay();

        $jobs = $job !== null
            ? collect([$job])
            : $workspace->jobs()->get(['id', 'title', 'status']);
        $jobIds = $jobs->pluck('id')->all();
        $titles = $jobs->pluck('title', 'id');

        $applications = EmployerJobApplication::query()
            ->whereIn('employer_job_id', $jobIds)
            ->get(['id', 'employer_job_id', 'stage', 'mock_score', 'created_at', 'graded_at']);
        // A subquery rather than an id list: a busy workspace can hold more
        // applications than a database allows bound parameters.
        $appIds = EmployerJobApplication::query()->select('id')->whereIn('employer_job_id', $jobIds);
        $jobOf = $applications->pluck('employer_job_id', 'id');

        $interviews = EmployerInterview::query()
            ->whereIn('employer_job_application_id', clone $appIds)
            ->get(['id', 'employer_job_application_id', 'round', 'round_name', 'status', 'overall_score', 'invited_at', 'started_at', 'submitted_at', 'graded_at']);

        $transitions = fn (): Builder => ApplicationStageTransition::query()->whereIn('employer_job_application_id', clone $appIds);

        $movedToday = $this->countByStage($transitions()->where('occurred_at', '>=', $this->today));
        $movedRecently = $this->countByStage($transitions()->where('occurred_at', '>=', $this->dayAgo));
        $enteredAt = $this->enteredAt($transitions());

        $runs = fn (): Builder => EmployerAutomationRun::query()
            ->whereIn('employer_job_application_id', clone $appIds)
            ->where('outcome', 'applied');
        $runsToday = $runs()->where('occurred_at', '>=', $this->today)->count();
        $runsRecently = $runs()->where('occurred_at', '>=', $this->dayAgo)->count();

        $byStage = $applications->groupBy(fn (EmployerJobApplication $a): string => $a->stage->value);
        $count = fn (string $stage): int => $byStage->get($stage, collect())->count();

        $stages = $this->stages($byStage, $interviews, $movedToday, $enteredAt, $applications);

        return [
            'kpis' => [
                'open_roles' => $jobs->filter(fn (EmployerJob $j): bool => $j->status === EmployerJobStatus::Published)->count(),
                'in_pipeline' => $applications->filter(fn (EmployerJobApplication $a): bool => ! $a->stage->isTerminal())->count(),
                'interviews_in_flight' => $interviews->filter(fn (EmployerInterview $i): bool => in_array($i->status->value, self::IN_FLIGHT, true))->count(),
                'offers' => $count('offer'),
                'hired' => $count('hired'),
            ],
            'stages' => $stages,
            'bots' => $this->bots($applications, $interviews, $count, $movedToday, $movedRecently, $runsToday, $runsRecently),
            'events' => $this->events($transitions(), $interviews, $jobOf, $titles),
        ];
    }

    /**
     * @param  Collection<string, Collection<int, EmployerJobApplication>>  $byStage
     * @param  Collection<int, EmployerInterview>  $interviews
     * @param  array<string, int>  $movedToday
     * @param  array<int, array<string, CarbonInterface>>  $enteredAt
     * @param  Collection<int, EmployerJobApplication>  $applications
     * @return list<array<string, mixed>>
     */
    private function stages(Collection $byStage, Collection $interviews, array $movedToday, array $enteredAt, Collection $applications): array
    {
        $out = [];

        foreach (EmployerApplicationStage::order() as $stage) {
            $key = $stage->value;
            $inStage = $byStage->get($key, collect());

            $avgScore = in_array($key, ['l1', 'l2'], true)
                ? $this->average($this->gradedInterviews($interviews, $key)->pluck('overall_score'))
                : $this->average($inStage->pluck('mock_score'));

            $days = $inStage->map(function (EmployerJobApplication $a) use ($enteredAt, $key): float {
                $since = $enteredAt[$a->id][$key] ?? $a->created_at ?? $this->now;

                return max(0, $since->diffInSeconds($this->now, true)) / 86_400;
            });

            $out[] = [
                'key' => $key,
                'label' => self::LABELS[$key],
                'count' => $inStage->count(),
                'avg_score' => $avgScore,
                'avg_days_in_stage' => $days->isEmpty() ? null : round((float) $days->avg(), 1),
                'moved_today' => $key === 'applied'
                    ? $applications->filter(fn (EmployerJobApplication $a): bool => $a->created_at !== null && $a->created_at->gte($this->today))->count()
                    : ($movedToday[$key] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, EmployerJobApplication>  $applications
     * @param  Collection<int, EmployerInterview>  $interviews
     * @param  \Closure(string): int  $count
     * @param  array<string, int>  $movedToday
     * @param  array<string, int>  $movedRecently
     * @return list<array<string, mixed>>
     */
    private function bots(Collection $applications, Collection $interviews, \Closure $count, array $movedToday, array $movedRecently, int $runsToday, int $runsRecently): array
    {
        $appliedToday = $applications->filter(fn (EmployerJobApplication $a): bool => $a->created_at !== null && $a->created_at->gte($this->today))->count();
        $appliedRecently = $applications->contains(fn (EmployerJobApplication $a): bool => $a->created_at !== null && $a->created_at->gte($this->dayAgo));
        $total = $applications->count();

        $gradedToday = $applications->filter(fn (EmployerJobApplication $a): bool => $a->graded_at !== null && $a->graded_at->gte($this->today))->count();
        $gradedRecently = $applications->contains(fn (EmployerJobApplication $a): bool => $a->graded_at !== null && $a->graded_at->gte($this->dayAgo));
        $screenAvg = $this->average($applications->pluck('mock_score'));
        $awaitingScreen = $applications->filter(fn (EmployerJobApplication $a): bool => $a->stage === EmployerApplicationStage::Applied && $a->graded_at === null)->count();

        $bots = [];

        $bots[] = $this->bot('sourcing', 'Sourcing bot', 'applied', $appliedRecently,
            $appliedRecently
                ? 'Taking in applications — '.$this->plural($appliedToday, 'new application').' today.'
                : ($total > 0 ? 'Waiting for new applicants. '.$this->plural($total, 'application').' received so far.' : 'Waiting for the first applicant.'),
            [['label' => 'Applications today', 'value' => $appliedToday], ['label' => 'Total applications', 'value' => $total]],
        );

        $bots[] = $this->bot('screener', 'AI screener', 'graded', $gradedRecently,
            $gradedRecently
                ? 'Scoring screening mocks — '.$this->plural($gradedToday, 'candidate').' graded today.'
                : ($awaitingScreen > 0 ? $this->plural($awaitingScreen, 'applicant').' yet to finish the screening mock.' : 'Nothing to screen right now.'),
            [['label' => 'Graded today', 'value' => $gradedToday], ['label' => 'Avg screening score', 'value' => $screenAvg]],
        );

        $awaitingReview = $count('graded');
        $shortlistActive = $runsRecently > 0 || ($movedRecently['shortlisted'] ?? 0) > 0;
        $bots[] = $this->bot('shortlist', 'Shortlist bot', 'shortlisted', $shortlistActive,
            $shortlistActive
                ? 'Applying your shortlist rules — '.$this->plural($runsToday, 'automatic move').' today.'
                : ($awaitingReview > 0 ? $this->plural($awaitingReview, 'screened candidate').' waiting for your review.' : 'No one waiting to be shortlisted.'),
            [['label' => 'Automation moves today', 'value' => $runsToday], ['label' => 'Shortlisted', 'value' => $count('shortlisted')]],
        );

        foreach (['l1' => 'L1', 'l2' => 'L2'] as $round => $label) {
            $ofRound = $interviews->filter(fn (EmployerInterview $i): bool => $i->round === $round);
            $inFlight = $ofRound->filter(fn (EmployerInterview $i): bool => in_array($i->status->value, self::IN_FLIGHT, true))->count();
            $graded = $this->gradedInterviews($interviews, $round);
            $active = $ofRound->contains(fn (EmployerInterview $i): bool => collect([$i->invited_at, $i->started_at, $i->submitted_at, $i->graded_at])
                ->contains(fn ($at): bool => $at !== null && $at->gte($this->dayAgo)));

            $bots[] = $this->bot("interviewer_{$round}", "AI interviewer · {$label}", $round, $active,
                $inFlight > 0
                    ? 'Running '.$this->plural($inFlight, "{$label} interview").'.'
                    : ($graded->isNotEmpty() ? $this->plural($graded->count(), "{$label} interview").' graded so far.' : "No {$label} interviews yet."),
                [
                    ['label' => 'In progress', 'value' => $inFlight],
                    ['label' => 'Graded', 'value' => $graded->count()],
                    ['label' => 'Avg score', 'value' => $this->average($graded->pluck('overall_score'))],
                ],
            );
        }

        $awaitingHuman = $count('human_round');
        $bot = $this->bot('scheduler', 'Interview scheduler', 'human_round', ($movedRecently['human_round'] ?? 0) > 0,
            $awaitingHuman > 0
                ? $this->plural($awaitingHuman, 'candidate').' waiting for a human interview — schedule them.'
                : 'No human rounds to schedule.',
            [['label' => 'Awaiting human round', 'value' => $awaitingHuman], ['label' => 'Moved in today', 'value' => $movedToday['human_round'] ?? 0]],
        );
        if ($awaitingHuman > 0) {
            $bot['status'] = 'needs';
        }
        $bots[] = $bot;

        $offers = $count('offer');
        $bots[] = $this->bot('offers', 'Offer desk', 'offer', ($movedRecently['offer'] ?? 0) > 0,
            $offers > 0 ? $this->plural($offers, 'offer').' out with candidates.' : 'No offers out right now.',
            [['label' => 'Open offers', 'value' => $offers], ['label' => 'Offers today', 'value' => $movedToday['offer'] ?? 0]],
        );

        $hired = $count('hired');
        $hiredToday = $movedToday['hired'] ?? 0;
        $bots[] = $this->bot('onboarding', 'Onboarding bot', 'hired', ($movedRecently['hired'] ?? 0) > 0,
            $hiredToday > 0
                ? 'Welcoming '.$this->plural($hiredToday, 'new hire').' today.'
                : ($hired > 0 ? $this->plural($hired, 'hire').' so far.' : 'No hires yet.'),
            [['label' => 'Hired', 'value' => $hired], ['label' => 'Hired today', 'value' => $hiredToday]],
        );

        return $bots;
    }

    /**
     * @param  list<array{label: string, value: int|float|null}>  $metrics
     * @return array<string, mixed>
     */
    private function bot(string $key, string $name, string $stage, bool $active, string $task, array $metrics): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'stage' => $stage,
            'status' => $active ? 'working' : 'idle',
            'task' => $task,
            'metrics' => $metrics,
        ];
    }

    /**
     * Latest stage moves and graded interviews, in job-title terms only.
     *
     * @param  Builder<ApplicationStageTransition>  $transitions
     * @param  Collection<int, EmployerInterview>  $interviews
     * @param  Collection<int, int>  $jobOf  application id => job id
     * @param  Collection<int, string>  $titles  job id => title
     * @return list<array<string, mixed>>
     */
    private function events(Builder $transitions, Collection $interviews, Collection $jobOf, Collection $titles): array
    {
        $title = fn (int $applicationId): string => (string) ($titles->get($jobOf->get($applicationId)) ?? 'a role');

        $moves = $transitions
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::EVENT_LIMIT)
            ->get(['id', 'employer_job_application_id', 'to_stage', 'actor_type', 'occurred_at'])
            ->map(fn (ApplicationStageTransition $t): array => [
                'occurred_at' => $t->occurred_at,
                'message' => $this->moveSentence($t->to_stage, $title($t->employer_job_application_id)),
                'stage' => $t->to_stage,
                'actor' => $this->actor($t->actor_type, $t->to_stage),
            ]);

        $graded = $interviews
            ->filter(fn (EmployerInterview $i): bool => $i->graded_at !== null)
            ->sortByDesc('graded_at')
            ->take(self::EVENT_LIMIT)
            ->map(fn (EmployerInterview $i): array => [
                'occurred_at' => $i->graded_at,
                'message' => ($i->round_name ?: strtoupper($i->round)).' interview graded'
                    .($i->overall_score !== null ? " {$i->overall_score}/100" : '')
                    .' for '.$title($i->employer_job_application_id),
                'stage' => $i->round,
                'actor' => 'AI interviewer',
            ]);

        return $moves->concat($graded->values())
            ->sortByDesc(fn (array $e): int => $e['occurred_at']->getTimestamp())
            ->take(self::EVENT_LIMIT)
            ->map(fn (array $e): array => [...$e, 'occurred_at' => $e['occurred_at']->toIso8601String()])
            ->values()
            ->all();
    }

    private function moveSentence(string $stage, string $title): string
    {
        return match ($stage) {
            'applied' => "New application for {$title}",
            'graded' => "A screening mock was scored for {$title}",
            'shortlisted' => "A candidate was shortlisted for {$title}",
            'l1' => "A candidate moved to the L1 interview for {$title}",
            'l2' => "A candidate moved to the L2 interview for {$title}",
            'human_round' => "A candidate is ready for a human round for {$title}",
            'offer' => "A candidate moved to offer for {$title}",
            'hired' => "A candidate was hired for {$title}",
            'rejected' => "A candidate was not taken forward for {$title}",
            'withdrawn' => "A candidate withdrew from {$title}",
            default => "A candidate moved to {$stage} for {$title}",
        };
    }

    private function actor(string $type, string $stage): string
    {
        return match ($type) {
            'rule' => 'Automation',
            'system' => $stage === 'graded' ? 'AI screener' : 'System',
            default => $stage === 'applied' ? 'Candidate' : 'Your team',
        };
    }

    /**
     * @param  Builder<ApplicationStageTransition>  $query
     * @return array<string, int>
     */
    private function countByStage(Builder $query): array
    {
        return $query
            ->selectRaw('to_stage, count(*) as total')
            ->groupBy('to_stage')
            ->pluck('total', 'to_stage')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * When each application last entered each stage.
     *
     * @param  Builder<ApplicationStageTransition>  $query
     * @return array<int, array<string, CarbonInterface>>
     */
    private function enteredAt(Builder $query): array
    {
        $out = [];

        $rows = $query
            ->selectRaw('employer_job_application_id as application_id, to_stage, MAX(occurred_at) as entered_at')
            ->groupBy('employer_job_application_id', 'to_stage')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $out[(int) $row->application_id][(string) $row->to_stage] = Carbon::parse((string) $row->entered_at);
        }

        return $out;
    }

    /**
     * @param  Collection<int, EmployerInterview>  $interviews
     * @return Collection<int, EmployerInterview>
     */
    private function gradedInterviews(Collection $interviews, string $round): Collection
    {
        return $interviews->filter(fn (EmployerInterview $i): bool => $i->round === $round
            && $i->status === EmployerInterviewStatus::Graded
            && $i->overall_score !== null)->values();
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function average(Collection $values): ?float
    {
        $present = $values->filter(fn ($v): bool => $v !== null);

        return $present->isEmpty() ? null : round((float) $present->avg(), 1);
    }

    private function plural(int $n, string $noun): string
    {
        return $n.' '.($n === 1 ? $noun : $noun.'s');
    }
}
