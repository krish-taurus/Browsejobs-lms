<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Models\EmployerJob;
use App\Models\SampleCandidate;
use Illuminate\Support\Collection;

/**
 * Scores synthetic candidates against a JD's skills the same way
 * LmsTalentMatcher scores real ones, so the Talent Pool's "preview with
 * sample data" toggle looks and behaves like the real thing. Entirely
 * separate data source, entirely separate class — real matching code is
 * never touched by this, and this is never called unless a request
 * explicitly opts in (see TalentPoolController::index()).
 */
final readonly class SampleTalentMatcher
{
    public function forJob(EmployerJob $job, int $limit = 12): Collection
    {
        $required = array_map(
            static fn (string $s) => mb_strtolower(trim($s)),
            (array) ($job->skills ?? []),
        );
        if ($required === []) {
            return collect();
        }

        return SampleCandidate::query()
            ->get()
            ->map(function (SampleCandidate $c) use ($required): ?array {
                $candidateSkills = array_map(
                    static fn (string $s) => mb_strtolower(trim($s)),
                    (array) ($c->skills ?? []),
                );
                $matched = array_values(array_intersect($required, $candidateSkills));
                $missing = array_values(array_diff($required, $candidateSkills));
                if ($matched === []) {
                    return null;
                }

                $skillPct = (int) round(count($matched) / count($required) * 100);
                $match = (int) round($skillPct * 0.5 + $c->ai_readiness_score * 0.3 + $c->cv_mock_score * 0.2);

                return [
                    'sample' => $c,
                    'match_score' => min(100, $match),
                    'skill_match_pct' => $skillPct,
                    'matched_skills' => $matched,
                    'missing_skills' => $missing,
                ];
            })
            ->filter()
            ->sortByDesc('match_score')
            ->take($limit)
            ->values();
    }
}
