<?php

declare(strict_types=1);

namespace App\Support\Cv;

/**
 * Free public ATS quick-check (homepage lead magnet): deterministic scoring
 * of raw pasted resume text — sections, contact info, action verbs,
 * quantified lines, length. No AI, no cost, safe unauthenticated. The full
 * structured AtsReport (per-bullet lint, JD match) stays inside the program.
 */
final class QuickAtsCheck
{
    private const ACTION_VERBS = [
        'built', 'designed', 'developed', 'implemented', 'optimised', 'optimized', 'automated',
        'led', 'created', 'delivered', 'reduced', 'improved', 'migrated', 'deployed', 'tested',
        'analysed', 'analyzed', 'engineered', 'integrated', 'debugged', 'solved',
    ];

    private const SECTIONS = ['experience', 'education', 'skill', 'project'];

    /** @return array{score: int, checks: list<array{label: string, pass: bool, hint: string}>} */
    public function run(string $text): array
    {
        $lower = mb_strtolower($text);
        $lines = collect(preg_split('/\r?\n/', trim($text)) ?: [])
            ->map(fn ($l) => trim((string) $l))->filter()->values();
        $words = str_word_count(strip_tags($text));

        $sectionsFound = collect(self::SECTIONS)->filter(fn ($s) => str_contains($lower, $s))->count();
        $hasEmail = preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $text) === 1;
        $hasPhone = preg_match('/(\+91[\s-]?)?[6-9]\d{9}/', preg_replace('/[\s-]/', '', $text) ?? '') === 1;
        $quantified = $lines->filter(fn (string $l) => preg_match('/\d/', $l) === 1)->count();
        $quantPct = $lines->isEmpty() ? 0 : (int) round($quantified / $lines->count() * 100);
        $verbLines = $lines->filter(function (string $l): bool {
            $first = mb_strtolower((string) preg_replace('/[^a-zA-Z].*$/', '', strtok(ltrim($l, "-•* \t"), ' ') ?: ''));

            return in_array($first, self::ACTION_VERBS, true);
        })->count();
        $lengthOk = $words >= 120 && $words <= 900;

        $checks = [
            ['label' => 'Core sections present', 'pass' => $sectionsFound >= 3,
                'hint' => 'ATS parsers look for Experience, Education, Skills and Projects headings — name them plainly.'],
            ['label' => 'Contact details parseable', 'pass' => $hasEmail && $hasPhone,
                'hint' => 'Put a plain email and a 10-digit mobile number in the header text, not in an image.'],
            ['label' => 'Bullets start with action verbs', 'pass' => $verbLines >= 3,
                'hint' => 'Open bullets with verbs like Built, Automated, Reduced — recruiters and parsers both scan for them.'],
            ['label' => 'Results are quantified', 'pass' => $quantPct >= 30,
                'hint' => 'Add numbers: rows processed, % faster, users served. Quantified lines read as evidence, not claims.'],
            ['label' => 'Length in the ATS sweet spot', 'pass' => $lengthOk,
                'hint' => 'Aim for one tight page (~150–900 words). Too short reads empty; too long gets truncated.'],
        ];

        $score = (int) round(collect($checks)->filter(fn ($c) => $c['pass'])->count() / count($checks) * 100);

        return ['score' => $score, 'checks' => $checks];
    }
}
