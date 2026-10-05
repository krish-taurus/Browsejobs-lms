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
 *
 * Two hard requirements to appear at all (Aug 2026, employer request after
 * the pool surfaced students with almost no real connection to the role —
 * e.g. a Data Analytics student showing up for a DevOps JD purely on
 * knowing "python"):
 * 1. A completed AI Readiness Interview (cv_mock_completed_at) — see
 *    caution in forJob()'s own comment: this can leave a JD's pool empty
 *    while platform-wide adoption of that interview is still low.
 * 2. Role relevance (roleRelevant()) — the JD's own title/role_family has
 *    to actually appear somewhere in the student's track/CV, not just an
 *    incidental skill in common. Skill overlap alone was letting almost
 *    any technical student surface for almost any technical JD.
 */
final readonly class LmsTalentMatcher
{
    /** Weightings for the composite match score (sums to 100). */
    private const W_SKILLS = 50;

    private const W_READINESS = 20;

    private const W_MOCK = 15;

    private const W_CV_READINESS = 15;

    /**
     * A word this short (role/course, "AI", "QA") is too generic to prove
     * relevance on its own — skip it rather than let it match everything.
     */
    private const MIN_ROLE_WORD_LENGTH = 4;

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

        // Restored hard gate (Aug 2026): must have completed the AI Readiness
        // Interview to appear at all — an employer request, made knowing this
        // can leave a JD's pool empty (or thin) while platform-wide
        // completions of that interview are still low. That tradeoff was
        // made explicitly, not accidentally — see StartCvReadinessMock for
        // the interview itself and where a student takes it.
        /** @var Collection<int, CvProfile> $profiles */
        $profiles = CvProfile::query()
            ->whereIn('user_id', $users->keys()->all())
            ->whereNotNull('cv_mock_completed_at')
            ->get()
            // A CV row can exist with nothing actually in it — require real
            // content too, not just a completed interview with an empty CV.
            ->filter(fn (CvProfile $p) => ! empty($p->data['summary'] ?? null)
                || ! empty($p->data['skills'] ?? [])
                || ! empty($p->data['experience'] ?? []));

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

        $roleWords = $this->roleWords($job);

        return $profiles
            ->map(function (CvProfile $profile) use ($required, $users, $scores, $mockAverages, $training, $roleWords): ?array {
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

                // A skill in common isn't enough — a Data Analytics student
                // knowing "python" shouldn't surface for a DevOps JD. The
                // JD's own title/role_family has to actually appear in the
                // student's track or their CV's most recent role.
                if (! $this->roleRelevant($roleWords, $profile, $training[$profile->user_id] ?? null)) {
                    return null;
                }

                $skillPct = (int) round(count($matched) / count($required) * 100);
                $readiness = (int) ($scores->get($profile->user_id)?->pri ?? 0);
                $mockRow = $mockAverages->get($profile->user_id);
                $mockAvg = $mockRow !== null ? (int) round((float) $mockRow->avg_score) : 0;
                // 0 when they haven't taken it — surfaces on the other three
                // components alone, just without this one's points.
                $cvReadiness = (int) ($profile->cv_mock_score ?? 0);

                $match = (int) round(
                    $skillPct * (self::W_SKILLS / 100)
                    + $readiness * (self::W_READINESS / 100)
                    + $mockAvg * (self::W_MOCK / 100)
                    + $cvReadiness * (self::W_CV_READINESS / 100)
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
                    'cv_mock_score' => $profile->cv_mock_score,
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
    private function roleWords(EmployerJob $job): array
    {
        $text = mb_strtolower(trim($job->title.' '.($job->role_family ?? '')));
        $words = preg_split('/[^a-z0-9]+/', $text) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $w) => mb_strlen($w) >= self::MIN_ROLE_WORD_LENGTH,
        )));
    }

    /**
     * True if the JD's own role shows up somewhere real for this student —
     * their enrolled/completed course, or their CV's own most recent job
     * title. A JD with no usable role words (title too short/generic) never
     * excludes anyone on this check; that's a judgement call in favour of
     * not silently hiding candidates over a JD that gave us nothing to work
     * with, not a loophole.
     *
     * @param  list<string>  $roleWords
     * @param  array{course: string, status: string}|null  $training
     */
    private function roleRelevant(array $roleWords, CvProfile $profile, ?array $training): bool
    {
        if ($roleWords === []) {
            return true;
        }

        $recentTitle = (string) ($profile->data['experience'][0]['title'] ?? '');
        $blob = mb_strtolower($recentTitle.' '.($training['course'] ?? ''));

        foreach ($roleWords as $word) {
            if (str_contains($blob, $word)) {
                return true;
            }
        }

        return false;
    }

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
