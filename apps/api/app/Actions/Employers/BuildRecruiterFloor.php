<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Enums\EmployerJobStatus;
use App\Models\ApplicationStageTransition;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\EmployerWorkspace;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The live AI Recruiter floor, laid out as the hiring pipeline the agents
 * run (client's 7 steps): job intake on WhatsApp → shortlist → BGV → final
 * shortlist → interview invites → L1 → L2 → client report.
 *
 * Every figure comes from real rows: WhatsApp chats, shortlist runs and
 * picks, applications, stage moves and interviews. An agent that is not
 * built yet says so (`live: false`) instead of pretending to work — BGV is
 * not connected, and the client report is the next step to build.
 *
 * Shown only to members of the workspace. A BrowseJobs student's contact
 * details are never part of this payload.
 */
final class BuildRecruiterFloor
{
    public const ZONES = [
        ['id' => 'intake', 'label' => 'Job intake'],
        ['id' => 'shortlist', 'label' => 'Shortlist'],
        ['id' => 'bgv', 'label' => 'BGV'],
        ['id' => 'final', 'label' => 'Final shortlist'],
        ['id' => 'invites', 'label' => 'Interview invites'],
        ['id' => 'l1', 'label' => 'L1 interview'],
        ['id' => 'l2', 'label' => 'L2 interview'],
        ['id' => 'report', 'label' => 'Client report'],
    ];

    private const SOURCE_LABEL = [
        'browsejobs' => 'BrowseJobs',
        'client_db' => 'Your database',
        'naukri' => 'Naukri',
    ];

    private const MAX_PEOPLE = 80;

    private CarbonInterface $now;

    /** @return array<string, mixed> */
    public function handle(EmployerWorkspace $workspace, ?int $jobId = null): array
    {
        $this->now = now();

        $jobs = $workspace->jobs()
            ->where('status', '!=', EmployerJobStatus::Closed->value)
            ->orderByDesc('published_at')->orderByDesc('id')
            ->get(['id', 'title', 'status', 'locations', 'remote', 'openings', 'experience_min_years', 'experience_max_years', 'published_at', 'created_at']);

        $job = ($jobId !== null ? $jobs->firstWhere('id', $jobId) : null)
            ?? $jobs->firstWhere('status', EmployerJobStatus::Published)
            ?? $jobs->first();

        $links = EmployerWhatsappLink::query()->where('employer_workspace_id', $workspace->id)->get();
        $messages = EmployerWhatsappMessage::query()
            ->whereIn('employer_whatsapp_link_id', $links->pluck('id'))
            ->latest('id')->limit(40)->get();

        $run = $job !== null
            ? JobShortlistRun::query()->where('employer_job_id', $job->id)->latest('id')->first()
            : null;
        $entries = $job !== null
            ? JobShortlistEntry::query()->where('employer_job_id', $job->id)->where('status', JobShortlistEntry::STATUS_SHORTLISTED)
                ->with(['employerCandidate', 'candidateUser:id,name'])->orderBy('rank')->get()
            : collect();
        $applications = $job !== null
            ? EmployerJobApplication::query()->where('employer_job_id', $job->id)->with('candidate:id,name')->get()
            : collect();
        $interviews = $applications->isEmpty()
            ? collect()
            : EmployerInterview::query()->whereIn('employer_job_application_id', $applications->pluck('id'))->get();
        $transitions = $applications->isEmpty()
            ? collect()
            : ApplicationStageTransition::query()->whereIn('employer_job_application_id', $applications->pluck('id'))
                ->latest('occurred_at')->limit(40)->get();

        $people = $this->people($entries, $applications, $interviews);
        $zoneCounts = collect(self::ZONES)->mapWithKeys(fn (array $z) => [$z['id'] => $people->where('zone', $z['id'])->count()]);

        return [
            'company' => $workspace->name,
            'jobs' => $jobs->map(fn (EmployerJob $j) => [
                'id' => $j->id,
                'title' => $j->title,
                'status' => $j->status->value,
            ])->values(),
            'job' => $job === null ? null : [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status->value,
                'where' => $job->remote ? 'Remote' : (implode(', ', $job->locations ?? []) ?: 'Any location'),
                'openings' => $job->openings,
                'experience' => $job->experience_min_years.($job->experience_max_years !== null ? '–'.$job->experience_max_years : '+').' yrs',
            ],
            'zones' => collect(self::ZONES)->map(fn (array $z) => [...$z, 'count' => $zoneCounts[$z['id']]])->values(),
            'agents' => $this->agents($job, $links, $messages, $run, $entries, $applications, $interviews),
            'people' => $people->take(self::MAX_PEOPLE)->values(),
            'activity' => $this->activity($jobs, $messages, $job, $run, $transitions, $interviews, $applications),
            'kpis' => [
                'jobs_live' => $jobs->where('status', EmployerJobStatus::Published)->count(),
                'shortlisted' => $entries->count(),
                'interviews' => $interviews->count(),
                'cleared' => $interviews->filter(fn (EmployerInterview $i) => $i->overall_score !== null && $i->overall_score >= 70)->count(),
            ],
            'generated_at' => $this->now->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, JobShortlistEntry>  $entries
     * @param  Collection<int, EmployerJobApplication>  $applications
     * @param  Collection<int, EmployerInterview>  $interviews
     * @return Collection<int, array<string, mixed>>
     */
    private function people(Collection $entries, Collection $applications, Collection $interviews): Collection
    {
        $byApp = $interviews->groupBy('employer_job_application_id');
        $appByUser = $applications->keyBy('candidate_id');
        $people = collect();

        foreach ($applications as $app) {
            if ($app->stage->isTerminal()) {
                continue;
            }
            $rounds = ($byApp->get($app->id) ?? collect())->keyBy('round');
            $l1 = $rounds->get('l1');
            $l2 = $rounds->get('l2');
            $entry = $entries->firstWhere('candidate_user_id', $app->candidate_id);

            $zone = match ($app->stage) {
                EmployerApplicationStage::Applied, EmployerApplicationStage::Graded => 'shortlist',
                EmployerApplicationStage::Shortlisted => 'final',
                EmployerApplicationStage::L1 => $l1 !== null && $l1->status === EmployerInterviewStatus::Invited ? 'invites' : 'l1',
                EmployerApplicationStage::L2 => $l2 !== null && $l2->status === EmployerInterviewStatus::Invited ? 'invites' : 'l2',
                default => 'report',
            };

            $timeline = [['label' => 'Applied', 'at' => $app->created_at?->toIso8601String()]];
            foreach (['l1' => $l1, 'l2' => $l2] as $key => $round) {
                if ($round !== null) {
                    $timeline[] = [
                        'label' => strtoupper($key).' '.str_replace('_', ' ', $round->status->value).($round->overall_score !== null ? " · {$round->overall_score}" : ''),
                        'at' => ($round->graded_at ?? $round->submitted_at ?? $round->invited_at)?->toIso8601String(),
                    ];
                }
            }

            $people->push([
                'id' => 'a'.$app->id,
                'name' => $app->candidate?->name ?? 'Candidate',
                'source' => self::SOURCE_LABEL[$entry?->source ?? 'browsejobs'],
                'zone' => $zone,
                'stage' => $app->stage->value,
                'match' => $entry?->match_score ?? $app->mock_score,
                'scores' => ['screen' => $app->mock_score, 'l1' => $l1?->overall_score, 'l2' => $l2?->overall_score],
                'matched_skills' => $entry !== null ? (array) ($entry->reasons['matched_skills'] ?? []) : [],
                'missing_skills' => $entry !== null ? (array) ($entry->reasons['missing_skills'] ?? []) : [],
                'facts' => [],
                'timeline' => $timeline,
            ]);
        }

        foreach ($entries as $entry) {
            if ($entry->candidate_user_id !== null && $appByUser->has($entry->candidate_user_id)) {
                continue; // already placed by their application
            }
            $c = $entry->employerCandidate;
            $people->push([
                'id' => 'e'.$entry->id,
                'name' => $c?->name ?? $entry->candidateUser?->name ?? 'Candidate',
                'source' => self::SOURCE_LABEL[$entry->source] ?? $entry->source,
                'zone' => 'shortlist',
                'stage' => 'shortlist',
                'match' => $entry->match_score,
                'scores' => ['screen' => null, 'l1' => null, 'l2' => null],
                'matched_skills' => (array) ($entry->reasons['matched_skills'] ?? []),
                'missing_skills' => (array) ($entry->reasons['missing_skills'] ?? []),
                'facts' => array_values(array_filter([
                    $c?->current_title,
                    $entry->reasons['experience'] ?? null,
                    $entry->reasons['location'] ?? null,
                    isset($entry->reasons['training']['course']) ? $entry->reasons['training']['course'].' · '.$entry->reasons['training']['status'] : null,
                ])),
                'timeline' => [['label' => "Shortlist agent ranked #{$entry->rank} · match {$entry->match_score}", 'at' => $entry->created_at?->toIso8601String()]],
            ]);
        }

        return $people->sortByDesc(fn (array $p) => $p['match'] ?? 0)->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function agents(
        ?EmployerJob $job,
        Collection $links,
        Collection $messages,
        ?JobShortlistRun $run,
        Collection $entries,
        Collection $applications,
        Collection $interviews,
    ): array {
        $recent = fn (?CarbonInterface $at, int $minutes) => $at !== null && $at->gt($this->now->copy()->subMinutes($minutes));
        $count = fn (string $stage) => $applications->filter(fn (EmployerJobApplication $a) => $a->stage->value === $stage)->count();

        // 1 · Job intake on WhatsApp
        $waiting = $links->where('state', EmployerWhatsappLink::STATE_CONFIRMING)->count();
        $lastIn = $messages->firstWhere('direction', 'in')?->created_at;
        $intake = $this->agent('intake', 'Job Poster', 'intake',
            $links->isEmpty() ? 'idle' : ($waiting > 0 ? 'approval' : ($recent($lastIn, 10) ? 'working' : 'idle')),
            $links->isEmpty()
                ? 'No phone connected yet. Scan the WhatsApp QR to post jobs by chat.'
                : ($waiting > 0
                    ? $this->plural($waiting, 'job draft').' waiting for the client to reply YES.'
                    : $this->plural($links->count(), 'phone').' connected. Message a role on WhatsApp to post it.'),
            $waiting > 0 ? 100 : ($links->isEmpty() ? 0 : 40),
            [['label' => 'Phones connected', 'value' => $links->count()], ['label' => 'Drafts waiting', 'value' => $waiting]],
        );

        // 2 · Shortlist agent
        $considered = collect($run?->considered ?? [])->sum(fn ($c) => (int) ($c['considered'] ?? 0));
        $topN = (int) ($run?->settings['top_n'] ?? 10);
        $shortlist = $this->agent('shortlist', 'Shortlist Agent', 'shortlist',
            $job === null ? 'idle' : ($run === null ? ($job->status === EmployerJobStatus::Published ? 'thinking' : 'idle') : ($recent($run->created_at, 3) ? 'working' : 'idle')),
            $job === null
                ? 'Waiting for a job to be posted.'
                : ($run === null
                    ? 'Has not run on this job yet. It runs on publish, or press Run on the AI shortlist tab.'
                    : 'Picked '.$entries->count()." of {$considered} looked at (".$this->bySource($run).').'),
            $run === null ? 0 : min(100, (int) round($entries->count() / max(1, $topN) * 100)),
            [['label' => 'Shortlisted', 'value' => $entries->count()], ['label' => 'Looked at', 'value' => $considered]],
        );

        // 3 · BGV — not connected
        $bgv = $this->agent('bgv', 'BGV Checker', 'bgv', 'idle',
            'Not connected yet — no background-verification provider is set up. Skipped for now.', 0, [], false);

        // 4 · Final shortlist
        $finalCount = $count('shortlisted');
        $final = $this->agent('final', 'Final Shortlist', 'final', $finalCount > 0 ? 'working' : 'idle',
            $finalCount > 0 ? $this->plural($finalCount, 'candidate').' on the final list for interviews.' : 'Nobody on the final list yet.',
            $finalCount > 0 ? 60 : 0,
            [['label' => 'On final list', 'value' => $finalCount]],
        );

        // 5 · Interview invites
        $invited = $interviews->filter(fn (EmployerInterview $i) => $i->status === EmployerInterviewStatus::Invited)->count();
        $invites = $this->agent('invites', 'Message Agent', 'invites', $invited > 0 ? 'working' : 'idle',
            $invited > 0 ? $this->plural($invited, 'candidate').' invited, waiting to start the interview.' : 'No interview invites out right now.',
            $invited > 0 ? 50 : 0,
            [['label' => 'Invited, not started', 'value' => $invited]],
        );

        // 6 · L1 / L2
        $rounds = [];
        foreach (['l1' => 'L1', 'l2' => 'L2'] as $key => $label) {
            $of = $interviews->where('round', $key);
            $inFlight = $of->filter(fn (EmployerInterview $i) => in_array($i->status, [EmployerInterviewStatus::InProgress, EmployerInterviewStatus::Submitted], true))->count();
            $graded = $of->filter(fn (EmployerInterview $i) => $i->overall_score !== null);
            $avg = $graded->isEmpty() ? null : (int) round((float) $graded->avg('overall_score'));
            $rounds[] = $this->agent($key, "{$label} Agent", $key, $inFlight > 0 ? 'working' : ($graded->isNotEmpty() && $recent($graded->max('graded_at'), 60) ? 'thinking' : 'idle'),
                $inFlight > 0
                    ? $this->plural($inFlight, "{$label} interview").' in progress.'
                    : ($graded->isNotEmpty() ? $this->plural($graded->count(), "{$label} interview")." scored · average {$avg}." : "No {$label} interviews yet."),
                $of->isEmpty() ? 0 : (int) round($graded->count() / $of->count() * 100),
                [['label' => 'In progress', 'value' => $inFlight], ['label' => 'Scored', 'value' => $graded->count()], ['label' => 'Average', 'value' => $avg ?? '—']],
            );
        }

        // 7 · Client report — next to build
        $done = $count('human_round') + $count('offer') + $count('hired');
        $report = $this->agent('report', 'Report Agent', 'report', $done > 0 ? 'approval' : 'idle',
            $done > 0
                ? $this->plural($done, 'candidate').' finished the rounds — ready for the client report.'
                : 'Sends the final candidate report to the client on WhatsApp once interviews finish.',
            $done > 0 ? 100 : 0,
            [['label' => 'Ready for report', 'value' => $done]],
            false,
        );

        return [$intake, $shortlist, $bgv, $final, $invites, ...$rounds, $report];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activity(Collection $jobs, Collection $messages, ?EmployerJob $job, ?JobShortlistRun $run, Collection $transitions, Collection $interviews, Collection $applications): array
    {
        $rows = collect();

        foreach ($messages as $m) {
            $rows->push([
                'at' => $m->created_at,
                'agent' => $m->direction === 'in' ? 'Client · WhatsApp' : 'Job Poster',
                'text' => Str::limit(trim(preg_replace('/\s+/', ' ', str_replace('*', '', $m->body)) ?? ''), 110),
            ]);
        }
        foreach ($jobs as $j) {
            if ($j->published_at !== null) {
                $rows->push(['at' => $j->published_at, 'agent' => 'Job Poster', 'text' => "{$j->title} went live."]);
            }
        }
        if ($run !== null && $job !== null) {
            $rows->push(['at' => $run->created_at, 'agent' => 'Shortlist Agent', 'text' => "Picked {$run->shortlisted_count} for {$job->title} (".$this->bySource($run).').']);
        }
        $names = $applications->mapWithKeys(fn (EmployerJobApplication $a) => [$a->id => $a->candidate?->name ?? 'A candidate']);
        foreach ($transitions as $t) {
            $rows->push(['at' => $t->occurred_at, 'agent' => $t->actor_type === 'rule' ? 'Automation' : 'Pipeline',
                'text' => ($names[$t->employer_job_application_id] ?? 'A candidate').' moved to '.str_replace('_', ' ', $t->to_stage).'.']);
        }
        foreach ($interviews as $i) {
            if ($i->graded_at !== null && $i->overall_score !== null) {
                $rows->push(['at' => $i->graded_at, 'agent' => strtoupper($i->round).' Agent',
                    'text' => ($names[$i->employer_job_application_id] ?? 'A candidate')." scored {$i->overall_score} in ".strtoupper($i->round).'.']);
            }
        }

        return $rows->filter(fn (array $r) => $r['at'] !== null)
            ->sortByDesc('at')->take(40)->values()
            ->map(fn (array $r, int $i) => ['id' => 'ev'.$i, 'at' => $r['at']->toIso8601String(), 'agent' => $r['agent'], 'text' => $r['text']])
            ->all();
    }

    /**
     * @param  list<array{label: string, value: int|string|null}>  $metrics
     * @return array<string, mixed>
     */
    private function agent(string $id, string $name, string $zone, string $state, string $task, int $progress, array $metrics, bool $live = true): array
    {
        return ['id' => $id, 'name' => $name, 'zone' => $zone, 'state' => $state, 'task' => $task, 'progress' => $progress, 'metrics' => $metrics, 'live' => $live];
    }

    private function bySource(JobShortlistRun $run): string
    {
        return collect($run->considered ?? [])
            ->map(fn ($c, $source) => (self::SOURCE_LABEL[$source] ?? $source).' '.(int) ($c['considered'] ?? 0))
            ->implode(', ') ?: 'no sources on';
    }

    private function plural(int $n, string $word): string
    {
        return $n.' '.Str::plural($word, $n);
    }
}
