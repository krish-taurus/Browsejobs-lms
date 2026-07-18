<?php

declare(strict_types=1);

namespace App\Support\Market;

use App\Models\MarketJd;
use Illuminate\Support\Carbon;

/**
 * Aggregates market-JD skill signal into demand trends (PRD §6.21). Pure
 * anonymised counts — a skill and how many parsed JDs named it, never anything
 * about a JD's source contact or a student. Shared by the admin trends view and
 * the syllabus-recommendation engine (P4.7b).
 */
final class SkillDemand
{
    /**
     * Skill demand for a course (or the whole tenant when $courseId is null),
     * over the trailing $days window. Returns rows ordered by frequency desc.
     *
     * @return list<array{skill: string, count: int, share: float}>
     */
    public function forCourse(?int $courseId, int $days = 180): array
    {
        $query = MarketJd::query()
            ->where('parse_status', MarketJd::STATUS_PARSED)
            ->where('ingested_at', '>=', Carbon::now()->subDays($days));

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        $counts = [];
        $totalJds = 0;

        /** @var MarketJd $jd */
        foreach ($query->select(['id', 'extracted_skills'])->cursor() as $jd) {
            $skills = $jd->extracted_skills ?? [];
            if ($skills === []) {
                continue;
            }
            $totalJds++;
            foreach (array_unique($skills) as $skill) {
                $counts[$skill] = ($counts[$skill] ?? 0) + 1;
            }
        }

        arsort($counts);

        $rows = [];
        foreach ($counts as $skill => $count) {
            $rows[] = [
                'skill' => (string) $skill,
                'count' => $count,
                'share' => $totalJds > 0 ? round($count / $totalJds, 3) : 0.0,
            ];
        }

        return $rows;
    }

    /** How many parsed JDs back the trend for a course (the sample size). */
    public function sampleSize(?int $courseId, int $days = 180): int
    {
        $query = MarketJd::query()
            ->where('parse_status', MarketJd::STATUS_PARSED)
            ->where('ingested_at', '>=', Carbon::now()->subDays($days));

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        return $query->count();
    }
}
