<?php

declare(strict_types=1);

namespace App\Support\Mocks;

use Illuminate\Support\Str;

/**
 * Which of an interview's competencies were really talked about. The grader
 * used to be handed the whole competency list and asked to score every one,
 * so a skill nobody asked about (SQL, in a pipeline-design interview) still
 * got a number and a line in the client's report. This is the plain check
 * that stops that: a competency counts as covered only when one of its
 * distinctive words (or the start of one, so "optimization" meets
 * "optimisation") appears in what the interviewer asked or the candidate
 * answered.
 *
 * Deliberately simple and explainable — it only ever removes a score the
 * transcript cannot support, never adds one.
 */
final class CompetencyCoverage
{
    /** Words that describe almost every competency and prove nothing on their own. */
    private const GENERIC = [
        'and', 'the', 'for', 'with', 'of', 'in', 'on', 'to', 'a', 'an', 'skills', 'skill', 'basics', 'basic', 'fundamentals',
        'general', 'knowledge', 'concepts', 'concept', 'data', 'engineering', 'development', 'design', 'management',
        'communication', 'problem', 'solving', 'role', 'fit',
    ];

    /** Words that mean the same ground as a competency name ("ELT pipeline" is ETL). */
    private const ALIASES = [
        'etl' => ['elt', 'pipeline'],
        'elt' => ['etl', 'pipeline'],
    ];

    /**
     * @param  list<string>  $competencies
     * @return array<string, bool> competency => talked about
     */
    public static function check(array $competencies, string $transcript): array
    {
        $haystack = Str::lower($transcript);
        $out = [];

        foreach ($competencies as $name) {
            $name = (string) $name;
            $stems = self::stems($name);

            // Nothing distinctive to look for ("Communication", "Role fit"): judged
            // on the whole conversation by the grader, so never ruled out here.
            if ($stems === []) {
                $out[$name] = true;

                continue;
            }

            $needles = collect($stems)->flatMap(fn (string $stem): array => [$stem, ...(self::ALIASES[$stem] ?? [])]);
            $out[$name] = $needles->contains(fn (string $needle): bool => str_contains($haystack, $needle));
        }

        return $out;
    }

    /** @return list<string> */
    public static function stems(string $competency): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($competency)) ?: [];

        $stems = [];
        foreach ($words as $word) {
            if ($word === '' || in_array($word, self::GENERIC, true)) {
                continue;
            }
            // SQL, ETL, AWS: short and exact. Longer words match on their first five letters.
            $stems[] = mb_strlen($word) <= 4 ? $word : mb_substr($word, 0, 5);
        }

        return array_values(array_unique($stems));
    }
}
