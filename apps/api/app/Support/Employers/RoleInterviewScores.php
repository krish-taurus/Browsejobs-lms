<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Models\EmployerJob;
use App\Models\MockInterview;

/**
 * A candidate's AI interview score for a JD's role: the best finished
 * interview that was either this JD's own interview (the one a student takes
 * from the job page or the screening invite) or any interview whose role
 * title contains every word of the JD title ("Data Engineer" counts for a
 * Data Engineer JD, "Data Analyst" does not).
 */
final class RoleInterviewScores
{
    /** A title word this short ("QA", "AI") is too generic to match roles on. */
    private const MIN_WORD_LENGTH = 4;

    /**
     * @param  list<int>  $userIds
     * @return array<int, int> user id => best score
     */
    public function forJob(EmployerJob $job, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return [];
        }

        $words = self::titleWords($job->title);
        $best = [];

        MockInterview::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('overall_score')
            ->with('blueprint:id,role_title,employer_job_id')
            ->get(['id', 'user_id', 'mock_blueprint_id', 'overall_score'])
            ->each(function (MockInterview $m) use ($job, $words, &$best): void {
                $title = mb_strtolower((string) $m->blueprint?->role_title);
                $sameJob = $m->blueprint?->employer_job_id === $job->id;
                $sameRole = $words !== [] && count(array_filter($words, fn (string $w) => str_contains($title, $w))) === count($words);

                if ($sameJob || $sameRole) {
                    $best[$m->user_id] = max($best[$m->user_id] ?? 0, (int) $m->overall_score);
                }
            });

        return $best;
    }

    /** @return list<string> */
    public static function titleWords(string $title): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9]+/', mb_strtolower($title)) ?: [],
            fn (string $w) => mb_strlen($w) >= self::MIN_WORD_LENGTH,
        )));
    }
}
