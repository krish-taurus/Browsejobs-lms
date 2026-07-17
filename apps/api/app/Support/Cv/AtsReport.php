<?php

declare(strict_types=1);

namespace App\Support\Cv;

use Illuminate\Support\Collection;

/**
 * Deterministic ATS suite (PRD §6.7): parse-simulation score, format lint,
 * quantified-bullet and action-verb coverage, and JD keyword match. No AI,
 * no cost — runs on every save so the score is always current.
 */
final class AtsReport
{
    private const ACTION_VERBS = [
        'built', 'designed', 'developed', 'implemented', 'optimised', 'optimized', 'automated',
        'led', 'created', 'delivered', 'reduced', 'improved', 'migrated', 'deployed', 'tested',
        'analysed', 'analyzed', 'engineered', 'integrated', 'debugged', 'solved', 'passed',
    ];

    private const STOPWORDS = [
        'and', 'the', 'for', 'with', 'you', 'are', 'our', 'will', 'have', 'that', 'this',
        'work', 'working', 'years', 'year', 'experience', 'strong', 'good', 'skills', 'must',
        'should', 'plus', 'etc', 'able', 'team', 'role', 'job', 'candidate', 'required',
        'requirements', 'preferred', 'knowledge', 'ability', 'excellent', 'looking',
    ];

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function for(array $content, ?string $jd = null): array
    {
        $bullets = collect((array) ($content['projects'] ?? []))
            ->flatMap(fn ($p) => (array) ($p['bullets'] ?? []))
            ->map(fn ($b) => trim((string) $b))
            ->filter()
            ->values();

        $lint = $this->lint($content, $bullets);
        $quantified = $bullets->isEmpty() ? 0
            : (int) round($bullets->filter(fn (string $b) => preg_match('/\d/', $b) === 1)->count() / $bullets->count() * 100);
        $verbs = $bullets->isEmpty() ? 0
            : (int) round($bullets->filter(fn (string $b) => in_array(
                mb_strtolower((string) preg_replace('/[^a-zA-Z].*$/', '', strtok($b, ' ') ?: '')),
                self::ACTION_VERBS,
                true,
            ))->count() / $bullets->count() * 100);

        // Parse simulation: what survives a dumb ATS — sections present,
        // parseable bullets, sane lengths. Lint findings each cost points.
        $score = 100 - count($lint) * 10;
        $score -= $quantified < 40 ? 10 : 0;
        $score -= $verbs < 50 ? 10 : 0;

        $report = [
            'parse_score' => max(0, min(100, $score)),
            'quantified_pct' => $quantified,
            'action_verb_pct' => $verbs,
            'lint' => $lint,
        ];

        if ($jd !== null && trim($jd) !== '') {
            $report['jd_match'] = $this->jdMatch($content, $jd);
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  Collection<int, string>  $bullets
     * @return list<string>
     */
    private function lint(array $content, $bullets): array
    {
        $lint = [];

        foreach (['headline', 'summary', 'skills'] as $section) {
            if (empty($content[$section])) {
                $lint[] = "Missing {$section} — parsers key on standard sections.";
            }
        }
        if (empty($content['projects'])) {
            $lint[] = 'No projects — complete coding labs to generate evidence.';
        }
        if ($bullets->contains(fn (string $b) => str_word_count($b) > 30)) {
            $lint[] = 'Some bullets exceed 30 words — parsers and recruiters both truncate.';
        }
        if (count((array) ($content['skills'] ?? [])) > 16) {
            $lint[] = 'Over 16 skills reads as keyword stuffing — keep the strongest.';
        }

        return $lint;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{pct: int, matched: list<string>, missing: list<string>}
     */
    private function jdMatch(array $content, string $jd): array
    {
        $keywords = collect(preg_split('/[^a-z0-9+#.]+/', mb_strtolower($jd)) ?: [])
            ->filter(fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOPWORDS, true))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(20);

        $cvText = mb_strtolower(json_encode($content, JSON_UNESCAPED_UNICODE) ?: '');

        [$matched, $missing] = $keywords->partition(fn (string $k) => str_contains($cvText, $k));

        return [
            'pct' => $keywords->isEmpty() ? 0 : (int) round($matched->count() / $keywords->count() * 100),
            'matched' => $matched->values()->all(),
            'missing' => $missing->values()->all(),
        ];
    }
}
