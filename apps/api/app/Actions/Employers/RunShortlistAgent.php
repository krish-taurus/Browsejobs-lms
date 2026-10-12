<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerJobStatus;
use App\Jobs\SendShortlistReport;
use App\Models\EmployerCandidate;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\ExternalTalentMatcher;
use App\Support\Employers\LmsTalentMatcher;
use App\Support\Employers\RoleInterviewScores;
use App\Support\Employers\ShortlistSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The shortlist agent (hiring pipeline step 2): for one JD, search every
 * source the client switched on — BrowseJobs students, the client's own
 * candidate database, their Naukri export — and score everyone the same
 * explainable way.
 *
 * How it decides (ShortlistSettings, default require_interview):
 *  - CV match ≥ 60 and AI interview ≥ 65 (first_cv_bar / first_interview_bar) → first priority;
 *  - CV ≥ 45 and interview ≥ 45 (second_cv_bar / second_interview_bar) → second priority;
 *  - CV ≥ second_cv_bar but no AI interview for the role yet → sent the AI
 *    screening interview on WhatsApp (SendScreeningInvites); when they finish
 *    it the agent runs again and places them;
 *  - anyone else is not shortlisted. Nobody gets on the list on a CV alone.
 * With require_interview off, the single match score and min_score apply.
 *
 * Each run replaces the previous list, except people the client removed by
 * hand: those stay removed and are never re-added. With auto_invite, the
 * people on the list are sent the interview invite (pick a day for L1)
 * straight away — never twice.
 */
final readonly class RunShortlistAgent
{
    /** How deep to read the student pool before cutting to top N. */
    private const STUDENT_POOL_DEPTH = 500;

    /** At most this many screening invites per run. */
    private const MAX_SCREENING = 50;

    public function __construct(
        private LmsTalentMatcher $students,
        private ExternalTalentMatcher $external,
        private RoleInterviewScores $interviews,
        private SendScreeningInvites $screening,
        private SendInterviewInvites $invites,
        private AuditLogger $audit,
    ) {}

    public function handle(EmployerJob $job, string $trigger, ?User $actor = null): JobShortlistRun
    {
        return app(TenantContext::class)->run($job->tenant, function () use ($job, $trigger, $actor): JobShortlistRun {
            $settings = ShortlistSettings::forJob($job);
            $considered = [];
            $candidates = collect();

            if ($settings->uses(JobShortlistEntry::SOURCE_BROWSEJOBS)) {
                // Students already on this JD's pipeline (screened, invited) are still read here.
                $pool = $this->students->forJob($job, self::STUDENT_POOL_DEPTH, $settings->studentsNeedReadiness, includeApplicants: true);
                $considered[JobShortlistEntry::SOURCE_BROWSEJOBS] = ['considered' => $pool->count(), 'matched' => $pool->count()];
                $candidates = $candidates->concat($pool->map(fn (array $m) => $this->fromStudent($m)));
            }

            $externalSources = array_values(array_intersect(EmployerCandidate::SOURCES, $settings->sources));
            if ($externalSources !== []) {
                $result = $this->external->forJob($job, $externalSources);
                foreach ($externalSources as $source) {
                    $considered[$source] = [
                        'considered' => $result['considered'][$source] ?? 0,
                        'matched' => $result['matches']->where('candidate.source', $source)->count(),
                    ];
                }
                $interviewOf = $this->interviews->forJob($job, $result['matches']->pluck('candidate.user_id')->filter()->all());
                $candidates = $candidates->concat($result['matches']->map(fn (array $m) => $this->fromExternal($m, $interviewOf)));
            }

            $removed = JobShortlistEntry::query()
                ->where('employer_job_id', $job->id)
                ->where('status', JobShortlistEntry::STATUS_REMOVED)
                ->pluck('candidate_key')
                ->flip();

            // Rejected or withdrawn on this JD's pipeline: never back on the list.
            $closed = EmployerJobApplication::query()
                ->where('employer_job_id', $job->id)
                ->whereIn('stage', [EmployerApplicationStage::Rejected->value, EmployerApplicationStage::Withdrawn->value])
                ->pluck('candidate_id')
                ->flip();

            /** @var Collection<int, array<string, mixed>> $eligible */
            $eligible = $candidates
                ->filter(fn (array $c) => ! $removed->has($c['candidate_key']) && ! $closed->has($c['account_user_id'] ?? 0))
                ->sortByDesc('match_score')
                ->unique('candidate_key')
                ->values();

            [$picked, $awaiting] = $settings->requireInterview
                ? $this->byCvAndInterview($eligible, $settings)
                : [$eligible->filter(fn (array $c) => $c['match_score'] >= $settings->minScore)->take($settings->topN)->values(), collect()];

            // Nobody cleared the bar and nobody is being screened: keep the closest few so the client sees who almost made it.
            $nearMisses = $picked->isEmpty() && $awaiting->isEmpty()
                ? $eligible
                    ->take(5)
                    ->map(fn (array $c) => [
                        'name' => $c['name'],
                        'source' => $c['source'],
                        'match_score' => $c['match_score'],
                        'cv_match' => $c['reasons']['cv_match'],
                        'interview_score' => $c['reasons']['interview_score'],
                        'matched_skills' => array_slice((array) ($c['reasons']['matched_skills'] ?? []), 0, 4),
                    ])
                    ->values()
                    ->all()
                : null;

            $awaitingRows = $awaiting->map(fn (array $c) => [
                'candidate_key' => $c['candidate_key'],
                'name' => $c['name'],
                'source' => $c['source'],
                'cv_match' => $c['reasons']['cv_match'],
                'candidate_user_id' => $c['candidate_user_id'],
                'employer_candidate_id' => $c['employer_candidate_id'],
            ])->values()->all();

            $run = DB::transaction(function () use ($job, $trigger, $actor, $settings, $considered, $picked, $nearMisses, $awaitingRows): JobShortlistRun {
                $run = JobShortlistRun::create([
                    'tenant_id' => $job->tenant_id,
                    'employer_job_id' => $job->id,
                    'trigger' => $trigger,
                    'triggered_by_user_id' => $actor?->id,
                    'settings' => $settings->toArray(),
                    'considered' => $considered,
                    'shortlisted_count' => $picked->count(),
                    'near_misses' => $nearMisses ?: null,
                    'awaiting' => $awaitingRows ?: null,
                ]);

                JobShortlistEntry::query()
                    ->where('employer_job_id', $job->id)
                    ->where('status', JobShortlistEntry::STATUS_SHORTLISTED)
                    ->delete();

                foreach ($picked as $i => $c) {
                    JobShortlistEntry::create([
                        'tenant_id' => $job->tenant_id,
                        'employer_job_id' => $job->id,
                        'job_shortlist_run_id' => $run->id,
                        'source' => $c['source'],
                        'candidate_user_id' => $c['candidate_user_id'],
                        'employer_candidate_id' => $c['employer_candidate_id'],
                        'candidate_key' => $c['candidate_key'],
                        'match_score' => $c['match_score'],
                        'rank' => $i + 1,
                        'reasons' => $c['reasons'],
                        'status' => JobShortlistEntry::STATUS_SHORTLISTED,
                    ]);
                }

                return $run;
            });

            // Contact people only for a live JD.
            $screened = ['queued' => 0, 'skipped' => []];
            $invited = ['queued' => 0, 'skipped' => []];
            if ($job->status === EmployerJobStatus::Published) {
                $screened = $this->screening->handle($job, $awaitingRows);

                if ($settings->autoInvite && $picked->isNotEmpty()) {
                    try {
                        $invited = $this->invites->handle($job, null);
                    } catch (ValidationException) {
                        // The invite message is not approved by Meta yet; the AI shortlist tab says so.
                        $invited = ['queued' => 0, 'skipped' => ['message not approved by Meta yet' => $picked->count()]];
                    }
                }
            }

            $this->audit->log('employer.shortlist_agent_ran', $run, [
                'job_id' => $job->id,
                'trigger' => $trigger,
                'shortlisted' => $picked->count(),
                'awaiting_interview' => count($awaitingRows),
                'screening_invites' => $screened,
                'interview_invites' => $invited,
                'sources' => $settings->sources,
            ], $actor);

            // Posted from WhatsApp: tell the client in the same chat who was found.
            if ($job->whatsapp_link_id !== null) {
                SendShortlistReport::dispatch($job->id);
            }

            return $run;
        });
    }

    /**
     * CV and AI interview each have to clear their bar: the first-priority
     * pair, else the second-priority pair. A good CV with no interview yet
     * goes to screening instead.
     *
     * @param  Collection<int, array<string, mixed>>  $eligible
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function byCvAndInterview(Collection $eligible, ShortlistSettings $settings): array
    {
        $graded = $eligible->map(function (array $c) use ($settings): array {
            $cv = (int) $c['reasons']['cv_match'];
            $interview = $c['reasons']['interview_score'];

            $c['tier'] = match (true) {
                $interview === null => null,
                $cv >= $settings->firstCvBar && $interview >= $settings->firstInterviewBar => 1,
                $cv >= $settings->secondCvBar && $interview >= $settings->secondInterviewBar => 2,
                default => null,
            };
            if ($c['tier'] !== null) {
                $c['match_score'] = (int) round(($cv + $interview) / 2);
                $c['reasons']['tier'] = $c['tier'];
            }

            return $c;
        });

        $picked = $graded
            ->whereNotNull('tier')
            ->sortBy([['tier', 'asc'], ['match_score', 'desc']])
            ->take($settings->topN)
            ->values();

        $awaiting = $graded
            ->filter(fn (array $c) => $c['reasons']['interview_score'] === null && $c['reasons']['cv_match'] >= $settings->secondCvBar)
            ->sortByDesc(fn (array $c) => $c['reasons']['cv_match'])
            ->take(min(self::MAX_SCREENING, $settings->topN * 2))
            ->values();

        return [$picked, $awaiting];
    }

    /**
     * @param  array<string, mixed>  $m  a LmsTalentMatcher row
     * @return array<string, mixed>
     */
    private function fromStudent(array $m): array
    {
        return [
            'source' => JobShortlistEntry::SOURCE_BROWSEJOBS,
            'candidate_user_id' => $m['user']->id,
            'employer_candidate_id' => null,
            'account_user_id' => $m['user']->id,
            'candidate_key' => 'u:'.$m['user']->id,
            'name' => (string) $m['user']->name,
            'match_score' => (int) $m['match_score'],
            'reasons' => [
                'cv_match' => (int) $m['skill_match_pct'],
                'interview_score' => $m['role_interview'],
                'skill_match_pct' => $m['skill_match_pct'],
                'matched_skills' => $m['matched_skills'],
                'missing_skills' => $m['missing_skills'],
                'readiness_index' => $m['readiness_index'],
                'mock_average' => $m['mock_average'],
                'ai_readiness' => $m['cv_mock_score'],
                'training' => $m['training'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $m  an ExternalTalentMatcher row
     * @param  array<int, int>  $interviewOf  best AI interview per account user id
     * @return array<string, mixed>
     */
    private function fromExternal(array $m, array $interviewOf): array
    {
        /** @var EmployerCandidate $candidate */
        $candidate = $m['candidate'];

        return [
            'source' => $candidate->source,
            'candidate_user_id' => null,
            'employer_candidate_id' => $candidate->id,
            'account_user_id' => $candidate->user_id,
            'candidate_key' => 'c:'.$candidate->id,
            'name' => $candidate->name,
            'match_score' => (int) $m['match_score'],
            'reasons' => [
                'cv_match' => (int) $m['skill_match_pct'],
                'interview_score' => $candidate->user_id !== null ? ($interviewOf[$candidate->user_id] ?? null) : null,
                'skill_match_pct' => $m['skill_match_pct'],
                'matched_skills' => $m['matched_skills'],
                'missing_skills' => $m['missing_skills'],
                'experience' => $m['experience'],
                'location' => $m['location'],
                'role_fit' => $m['role_fit'],
            ],
        ];
    }
}
