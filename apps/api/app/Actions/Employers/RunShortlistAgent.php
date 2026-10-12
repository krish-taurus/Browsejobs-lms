<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\EmployerCandidate;
use App\Models\EmployerJob;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\ExternalTalentMatcher;
use App\Support\Employers\LmsTalentMatcher;
use App\Support\Employers\ShortlistSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The shortlist agent (hiring pipeline step 2): for one JD, search every
 * source the client switched on — BrowseJobs students, the client's own
 * candidate database, their Naukri export — score everyone the same
 * explainable way, and keep the top N at or above the minimum score.
 *
 * Each run replaces the previous list, except people the client removed by
 * hand: those stay removed and are never re-added. Nobody is messaged here;
 * contacting the shortlist is a later, separate step.
 */
final readonly class RunShortlistAgent
{
    /** How deep to read the student pool before cutting to top N. */
    private const STUDENT_POOL_DEPTH = 500;

    public function __construct(
        private LmsTalentMatcher $students,
        private ExternalTalentMatcher $external,
        private AuditLogger $audit,
    ) {}

    public function handle(EmployerJob $job, string $trigger, ?User $actor = null): JobShortlistRun
    {
        return app(TenantContext::class)->run($job->tenant, function () use ($job, $trigger, $actor): JobShortlistRun {
            $settings = ShortlistSettings::forJob($job);
            $considered = [];
            $candidates = collect();

            if ($settings->uses(JobShortlistEntry::SOURCE_BROWSEJOBS)) {
                $pool = $this->students->forJob($job, self::STUDENT_POOL_DEPTH);
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
                $candidates = $candidates->concat($result['matches']->map(fn (array $m) => $this->fromExternal($m)));
            }

            $removed = JobShortlistEntry::query()
                ->where('employer_job_id', $job->id)
                ->where('status', JobShortlistEntry::STATUS_REMOVED)
                ->pluck('candidate_key')
                ->flip();

            /** @var Collection<int, array<string, mixed>> $picked */
            $picked = $candidates
                ->filter(fn (array $c) => $c['match_score'] >= $settings->minScore && ! $removed->has($c['candidate_key']))
                ->sortByDesc('match_score')
                ->unique('candidate_key')
                ->take($settings->topN)
                ->values();

            $run = DB::transaction(function () use ($job, $trigger, $actor, $settings, $considered, $picked): JobShortlistRun {
                $run = JobShortlistRun::create([
                    'tenant_id' => $job->tenant_id,
                    'employer_job_id' => $job->id,
                    'trigger' => $trigger,
                    'triggered_by_user_id' => $actor?->id,
                    'settings' => $settings->toArray(),
                    'considered' => $considered,
                    'shortlisted_count' => $picked->count(),
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

            $this->audit->log('employer.shortlist_agent_ran', $run, [
                'job_id' => $job->id,
                'trigger' => $trigger,
                'shortlisted' => $picked->count(),
                'sources' => $settings->sources,
            ], $actor);

            return $run;
        });
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
            'candidate_key' => 'u:'.$m['user']->id,
            'match_score' => (int) $m['match_score'],
            'reasons' => [
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
     * @return array<string, mixed>
     */
    private function fromExternal(array $m): array
    {
        /** @var EmployerCandidate $candidate */
        $candidate = $m['candidate'];

        return [
            'source' => $candidate->source,
            'candidate_user_id' => null,
            'employer_candidate_id' => $candidate->id,
            'candidate_key' => 'c:'.$candidate->id,
            'match_score' => (int) $m['match_score'],
            'reasons' => [
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
