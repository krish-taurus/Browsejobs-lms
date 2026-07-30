<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\CvProfile;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\MockInterview;
use App\Models\StudentScore;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Matches trained BrowseJobs students to an employer JD (PRD-E F7).
 *
 * This is the platform's structural advantage: these candidates already
 * have a built CV, a mastery record, mock history, and a placement
 * readiness index — evidence no external job board can offer. Matching
 * is deterministic and explainable (skill overlap + readiness + mock
 * performance), never a black box, so the employer always sees WHY a
 * student surfaced and what the gap is.
 *
 * Deliberately NOT an AI call: it runs per JD view, must be instant and
 * free, and the inputs are already structured.
 */
final readonly class LmsTalentMatcher
{
    /** Weightings for the composite match score (sums to 100). */
    private const W_SKILLS = 60;

    private const W_READINESS = 25;

    private const W_MOCK = 15;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forJob(EmployerJob $job, int $limit = 25): Collection
    {
        $required = $this->normalize($job->skills ?? []);

        if ($required === []) {
            return collect();
        }

        // Students who already applied are shown in the pipeline, not the pool.
        $applied = EmployerJobApplication::query()
            ->where('employer_job_id', $job->id)
            ->pluck('candidate_id')
            ->all();

        // Tenant scoping comes from the users query (CvProfile has a nullable,
        // unscoped tenant_id), so the pool can never cross tenants.
        $users = User::query()
            ->where('user_type', 'student')
            ->when($applied !== [], fn ($query) => $query->whereKeyNot($applied))
            ->get()
            ->keyBy('id');

        if ($users->isEmpty()) {
            return collect();
        }

        /** @var Collection<int, CvProfile> $profiles */
        $profiles = CvProfile::query()->whereIn('user_id', $users->keys()->all())->get();

        if ($profiles->isEmpty()) {
            return collect();
        }

        $userIds = $profiles->pluck('user_id')->all();
        $scores = StudentScore::query()->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $mockAverages = MockInterview::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('overall_score')
            ->selectRaw('user_id, avg(overall_score) as avg_score, count(*) as attempts')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
        $training = $this->trainingByUser($userIds);

        return $profiles
            ->map(function (CvProfile $profile) use ($required, $users, $scores, $mockAverages, $training): ?array {
                $user = $users->get($profile->user_id);
                if ($user === null) {
                    return null;
                }

                $studentSkills = $this->normalize((array) ($profile->data['skills'] ?? []));
                $matched = array_values(array_intersect($required, $studentSkills));
                $missing = array_values(array_diff($required, $studentSkills));

                if ($matched === []) {
                    return null;
                }

                $skillPct = (int) round(count($matched) / count($required) * 100);
                $readiness = (int) ($scores->get($profile->user_id)?->pri ?? 0);
                $mockRow = $mockAverages->get($profile->user_id);
                $mockAvg = $mockRow !== null ? (int) round((float) $mockRow->avg_score) : 0;

                $match = (int) round(
                    $skillPct * (self::W_SKILLS / 100)
                    + $readiness * (self::W_READINESS / 100)
                    + $mockAvg * (self::W_MOCK / 100)
                );

                return [
                    'user' => $user,
                    'match_score' => min(100, $match),
                    'skill_match_pct' => $skillPct,
                    'matched_skills' => $matched,
                    'missing_skills' => $missing,
                    'readiness_index' => $readiness,
                    'mock_average' => $mockAvg,
                    'mock_attempts' => $mockRow !== null ? (int) $mockRow->attempts : 0,
                    'training' => $training[$profile->user_id] ?? null,
                    'cv_ready' => ! empty($profile->data['summary'] ?? null) || ! empty($profile->data['skills'] ?? []),
                ];
            })
            ->filter()
            ->sortByDesc('match_score')
            ->take($limit)
            ->values();
    }

    /**
     * Course + completion status per student, so the employer can see
     * "trained on Data Engineering, completed" rather than a bare name.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{course: string, status: string}>
     */
    private function trainingByUser(array $userIds): array
    {
        return BatchMember::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', [BatchMemberStatus::Enrolled->value, BatchMemberStatus::Completed->value])
            ->with('batch.course')
            ->get()
            ->groupBy('user_id')
            ->map(function ($memberships) {
                // Prefer a completed programme over an in-progress one.
                $best = $memberships->sortByDesc(
                    fn ($member) => $member->status === BatchMemberStatus::Completed ? 1 : 0
                )->first();

                return [
                    'course' => (string) ($best->batch?->course?->name ?? 'BrowseJobs programme'),
                    'status' => is_object($best->status) ? $best->status->value : (string) $best->status,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $skills
     * @return list<string>
     */
    private function normalize(array $skills): array
    {
        $out = [];
        foreach ($skills as $skill) {
            $clean = mb_strtolower(trim((string) $skill));
            if ($clean !== '') {
                $out[] = $clean;
            }
        }

        return array_values(array_unique($out));
    }
}
