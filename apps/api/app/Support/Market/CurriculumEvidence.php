<?php

declare(strict_types=1);

namespace App\Support\Market;

use App\Models\Course;
use App\Models\RealInterviewQuestion;
use App\Support\Syllabus\SyllabusHasher;

/**
 * Fuses the Advice-Graph signals (PRD §6.21) into one evidence bundle for a
 * course: (a) job-market skill demand, (b) real-interview-bank topic frequency,
 * (c) where our candidates actually struggle, and (d) what the current syllabus
 * already covers. Anonymised aggregates only — never per-student rows. Consumed
 * by the recommendation generator and shown to the trainer as the "why".
 */
final class CurriculumEvidence
{
    public function __construct(
        private readonly SkillDemand $demand,
        private readonly SyllabusHasher $hasher,
    ) {}

    /**
     * @return array{
     *   course: string,
     *   outline: string,
     *   covered_terms: list<string>,
     *   market_demand: list<array{skill: string, count: int, share: float}>,
     *   market_sample: int,
     *   interview_topics: list<array{topic: string, frequency: int}>,
     *   failure_points: list<array{topic: string, count: int}>
     * }
     */
    public function forCourse(Course $course, int $days = 180): array
    {
        $outline = $this->hasher->outline($course);

        return [
            'course' => (string) $course->name,
            'outline' => $outline !== '' ? $outline : 'No modules defined yet.',
            'covered_terms' => $this->coveredTerms($outline),
            'market_demand' => array_slice($this->demand->forCourse($course->id, $days), 0, 25),
            'market_sample' => $this->demand->sampleSize($course->id, $days),
            'interview_topics' => $this->interviewTopics($course->id),
            'failure_points' => $this->failurePoints($course->id),
        ];
    }

    /**
     * Approved-bank topic frequency: how often each topic actually shows up in
     * real interviews for this course, weighted by asked_count.
     *
     * @return list<array{topic: string, frequency: int}>
     */
    private function interviewTopics(int $courseId): array
    {
        $freq = [];
        RealInterviewQuestion::query()
            ->where('course_id', $courseId)
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->select(['topic_tags', 'asked_count'])
            ->cursor()
            ->each(function (RealInterviewQuestion $q) use (&$freq): void {
                foreach ($q->topic_tags ?? [] as $tag) {
                    $t = mb_strtolower(trim((string) $tag));
                    if ($t !== '') {
                        $freq[$t] = ($freq[$t] ?? 0) + max(1, $q->asked_count);
                    }
                }
            });

        arsort($freq);

        return $this->rows($freq, 'frequency', 20);
    }

    /**
     * Where candidates struggle: approved questions that recorded struggle points
     * or a rejection outcome, grouped by topic. The sharpest gap signal.
     *
     * @return list<array{topic: string, count: int}>
     */
    private function failurePoints(int $courseId): array
    {
        $counts = [];
        RealInterviewQuestion::query()
            ->where('course_id', $courseId)
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->where(function ($q) {
                $q->whereNotNull('struggle_points')
                    ->orWhereIn('outcome', ['rejected', 'no_offer', 'failed']);
            })
            ->select(['topic_tags'])
            ->cursor()
            ->each(function (RealInterviewQuestion $q) use (&$counts): void {
                foreach ($q->topic_tags ?? [] as $tag) {
                    $t = mb_strtolower(trim((string) $tag));
                    if ($t !== '') {
                        $counts[$t] = ($counts[$t] ?? 0) + 1;
                    }
                }
            });

        arsort($counts);

        return $this->rows($counts, 'count', 15);
    }

    /**
     * Lowercased terms the current curriculum already names, so the generator can
     * tell "absent from the syllabus" from "already covered".
     *
     * @return list<string>
     */
    private function coveredTerms(string $outline): array
    {
        $words = preg_split('/[^a-z0-9+#.]+/', mb_strtolower($outline)) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 2)));
    }

    /**
     * @param  array<string, int>  $map
     * @return list<array<string, mixed>>
     */
    private function rows(array $map, string $valueKey, int $limit): array
    {
        $rows = [];
        foreach (array_slice($map, 0, $limit, true) as $topic => $value) {
            $rows[] = ['topic' => (string) $topic, $valueKey => $value];
        }

        return $rows;
    }
}
