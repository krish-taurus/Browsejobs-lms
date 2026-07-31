<?php

declare(strict_types=1);

namespace App\Support\Roles;

/**
 * Reader for the shared role vocabulary (PRD-E F14).
 *
 * Everything that offers a title or skill dropdown goes through here, so
 * the string a JD names is the same string the mock weights and the talent
 * matcher compares. Free-typed skills still work — an employer hiring for
 * something we have not catalogued must never be blocked — but the
 * suggestions keep the common cases aligned.
 */
final class RoleTaxonomy
{
    /**
     * Every family with its titles and skills.
     *
     * @return list<array{key: string, label: string, sector: string, titles: list<string>, core: list<string>, optional: list<string>}>
     */
    public function families(): array
    {
        $out = [];

        foreach ((array) config('role_taxonomy.families', []) as $key => $family) {
            $out[] = [
                'key' => (string) $key,
                'label' => (string) ($family['label'] ?? $key),
                'sector' => (string) ($family['sector'] ?? 'other'),
                'titles' => array_values((array) ($family['titles'] ?? [])),
                'core' => array_values((array) ($family['core'] ?? [])),
                'optional' => array_values((array) ($family['optional'] ?? [])),
            ];
        }

        return $out;
    }

    /**
     * Flat, de-duplicated title list for an autocomplete.
     *
     * @return list<array{title: string, family: string, sector: string}>
     */
    public function titles(): array
    {
        $out = [];

        foreach ($this->families() as $family) {
            foreach ($family['titles'] as $title) {
                $out[] = ['title' => $title, 'family' => $family['key'], 'sector' => $family['sector']];
            }
        }

        return $out;
    }

    /**
     * The family a title belongs to, matched case-insensitively and then by
     * containment so "Senior Data Engineer" still resolves to Data Engineer.
     * Null when nothing matches, which is a legitimate answer.
     *
     * @return array{key: string, label: string, sector: string, titles: list<string>, core: list<string>, optional: list<string>}|null
     */
    public function familyForTitle(string $title): ?array
    {
        $needle = mb_strtolower(trim($title));

        if ($needle === '') {
            return null;
        }

        foreach ($this->families() as $family) {
            foreach ($family['titles'] as $known) {
                if (mb_strtolower($known) === $needle) {
                    return $family;
                }
            }
        }

        // Longest known title first, so "Data Engineer" does not win over
        // "Analytics Engineer" inside "Senior Analytics Engineer".
        $candidates = [];
        foreach ($this->families() as $family) {
            foreach ($family['titles'] as $known) {
                $candidates[] = [mb_strlen($known), mb_strtolower($known), $family];
            }
        }
        usort($candidates, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        foreach ($candidates as [, $known, $family]) {
            if (str_contains($needle, $known)) {
                return $family;
            }
        }

        return null;
    }

    /**
     * Suggested skills for a title: core first, then optional.
     *
     * @return array{core: list<string>, optional: list<string>}
     */
    public function skillsForTitle(string $title): array
    {
        $family = $this->familyForTitle($title);

        return [
            'core' => $family['core'] ?? [],
            'optional' => $family['optional'] ?? [],
        ];
    }

    /**
     * @return list<array{key: string, label: string, hint: string}>
     */
    public function competencies(): array
    {
        return array_values((array) config('role_taxonomy.competencies', []));
    }

    /**
     * @return list<array{key: string, label: string, hint: string}>
     */
    public function questionFormats(): array
    {
        return array_values((array) config('role_taxonomy.question_formats', []));
    }
}
