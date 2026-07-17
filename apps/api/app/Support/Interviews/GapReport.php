<?php

declare(strict_types=1);

namespace App\Support\Interviews;

use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\RealInterviewQuestion;
use App\Models\User;

/**
 * Mock-vs-real gap report (PRD §6.6): "Real DE interviews weight SQL
 * optimisation 30% — you're at 55%." Deterministic — real weights come from
 * approved-bank topic frequencies (asked_count), the student side from their
 * BEST completed mock's competency scores. No AI, no cost, always current.
 */
final class GapReport
{
    private const TOP_TOPICS = 8;

    /**
     * @return array{role_title: string|null, items: list<array{topic: string, real_weight_pct: int, your_score: int|null, gap: bool}>}
     */
    public function for(User $student): array
    {
        $best = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('status', MockInterview::STATUS_COMPLETED)
            ->orderByDesc('overall_score')
            ->first();

        $blueprint = $best?->blueprint ?? $this->fallbackBlueprint($student);
        if ($blueprint === null) {
            return ['role_title' => null, 'items' => []];
        }

        $weights = $this->topicWeights($blueprint);
        if ($weights === []) {
            return ['role_title' => $blueprint->role_title, 'items' => []];
        }

        $competencies = collect($best?->scorecard['competencies'] ?? [])
            ->filter(fn ($c) => is_array($c) && isset($c['name'], $c['score']));

        $items = [];
        foreach ($weights as $topic => $pct) {
            $score = $competencies
                ->first(fn (array $c) => $this->matches($topic, (string) $c['name']))['score'] ?? null;
            $score = $score !== null ? (int) $score : null;

            $items[] = [
                'topic' => $topic,
                'real_weight_pct' => $pct,
                'your_score' => $score,
                // A gap worth flagging: heavily-asked topic where the student is
                // untested or scored below 70 (the human-gate bar).
                'gap' => $score === null || $score < 70,
            ];
        }

        return ['role_title' => $blueprint->role_title, 'items' => $items];
    }

    /**
     * Frequency-weighted topics from the approved bank for this course/role.
     *
     * @return array<string, int> topic => weight pct
     */
    private function topicWeights(MockBlueprint $blueprint): array
    {
        $counts = [];

        RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->where(fn ($q) => $q
                ->where('course_id', $blueprint->course_id)
                ->orWhere('role_title', $blueprint->role_title))
            ->get(['topic_tags', 'asked_count'])
            ->each(function (RealInterviewQuestion $question) use (&$counts): void {
                foreach ($question->topic_tags as $tag) {
                    $counts[$tag] = ($counts[$tag] ?? 0) + $question->asked_count;
                }
            });

        if ($counts === []) {
            return [];
        }

        arsort($counts);
        $counts = array_slice($counts, 0, self::TOP_TOPICS, preserve_keys: true);
        $total = array_sum($counts);

        return array_map(fn (int $n) => (int) round($n / $total * 100), $counts);
    }

    /** Loose topic↔competency matching: "sql optimisation" hits "SQL & data modelling". */
    private function matches(string $topic, string $competency): bool
    {
        $topic = mb_strtolower($topic);
        $competency = mb_strtolower($competency);

        if (str_contains($competency, $topic) || str_contains($topic, $competency)) {
            return true;
        }

        foreach (preg_split('/[^a-z0-9+#]+/', $topic) ?: [] as $word) {
            if (mb_strlen($word) >= 3 && str_contains($competency, $word)) {
                return true;
            }
        }

        return false;
    }

    private function fallbackBlueprint(User $student): ?MockBlueprint
    {
        $interview = MockInterview::query()
            ->where('user_id', $student->id)
            ->latest('id')
            ->first();

        return $interview?->blueprint;
    }
}
