<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Models\EmployerCandidate;
use App\Models\EmployerJob;
use Illuminate\Support\Collection;

/**
 * Matches the client's own candidates (their database / Naukri export) to a
 * JD. Same spirit as LmsTalentMatcher — deterministic and explainable, no AI
 * call — but built on what a recruiter sheet actually holds: skills, title,
 * experience and location, not training or mock history.
 *
 * Score (0–100): skills 60, experience 20, location 10, role fit 10.
 * A skill counts if it is in the Key Skills column or named in the title /
 * headline, so a sparse skills column doesn't hide a good person. Nobody
 * with zero matching skills is returned.
 */
final readonly class ExternalTalentMatcher
{
    private const W_SKILLS = 60;

    private const W_EXPERIENCE = 20;

    private const W_LOCATION = 10;

    private const W_ROLE = 10;

    private const MIN_ROLE_WORD_LENGTH = 4;

    /**
     * @param  list<string>  $sources  client_db and/or naukri
     * @return array{considered: array<string, int>, matches: Collection<int, array<string, mixed>>}
     */
    public function forJob(EmployerJob $job, array $sources): array
    {
        $required = $this->normalize($job->skills ?? []);
        $sources = array_values(array_intersect(EmployerCandidate::SOURCES, $sources));

        $considered = array_fill_keys($sources, 0);
        if ($required === [] || $sources === []) {
            return ['considered' => $considered, 'matches' => collect()];
        }

        $roleWords = $this->roleWords($job);
        $matches = collect();

        EmployerCandidate::query()
            ->where('employer_workspace_id', $job->employer_workspace_id)
            ->whereIn('source', $sources)
            ->chunkById(500, function (Collection $chunk) use ($job, $required, $roleWords, &$considered, $matches): void {
                foreach ($chunk as $candidate) {
                    $considered[$candidate->source]++;
                    $match = $this->score($job, $candidate, $required, $roleWords);
                    if ($match !== null) {
                        $matches->push($match);
                    }
                }
            });

        return ['considered' => $considered, 'matches' => $matches];
    }

    /**
     * @param  list<string>  $required
     * @param  list<string>  $roleWords
     * @return array<string, mixed>|null
     */
    private function score(EmployerJob $job, EmployerCandidate $candidate, array $required, array $roleWords): ?array
    {
        $own = $this->normalize($candidate->skills ?? []);
        $blob = ' '.mb_strtolower(implode(' ', array_filter([
            $candidate->current_title, $candidate->summary, implode(' ', $own),
        ]))).' ';

        $matched = [];
        foreach ($required as $skill) {
            if (in_array($skill, $own, true) || $this->mentions($blob, $skill)) {
                $matched[] = $skill;
            }
        }
        if ($matched === []) {
            return null;
        }
        $missing = array_values(array_diff($required, $matched));
        $skillPct = (int) round(count($matched) / count($required) * 100);

        [$expPct, $expNote] = $this->experienceFit($job, $candidate->experience_years !== null ? (float) $candidate->experience_years : null);
        [$locPct, $locNote] = $this->locationFit($job, $candidate->location);
        $roleFit = $roleWords === [] || $this->anyMention(mb_strtolower(' '.$candidate->current_title.' '.$candidate->summary.' '), $roleWords);

        $score = (int) round(
            $skillPct * self::W_SKILLS / 100
            + $expPct * self::W_EXPERIENCE / 100
            + $locPct * self::W_LOCATION / 100
            + ($roleFit ? 100 : 0) * self::W_ROLE / 100
        );

        return [
            'candidate' => $candidate,
            'match_score' => min(100, $score),
            'skill_match_pct' => $skillPct,
            'matched_skills' => $matched,
            'missing_skills' => $missing,
            'experience' => $expNote,
            'location' => $locNote,
            'role_fit' => $roleFit,
        ];
    }

    /** @return array{0: int, 1: string} */
    private function experienceFit(EmployerJob $job, ?float $years): array
    {
        if ($years === null) {
            return [50, 'Experience not given'];
        }

        $min = (float) $job->experience_min_years;
        $max = $job->experience_max_years !== null ? (float) $job->experience_max_years : null;
        $label = rtrim(rtrim(number_format($years, 1), '0'), '.').' yrs';

        if ($years >= $min && ($max === null || $years <= $max)) {
            return [100, "{$label} — in range"];
        }
        if ($years < $min) {
            return $min - $years <= 1 ? [60, "{$label} — just under"] : [20, "{$label} — below range"];
        }

        return [70, "{$label} — above range"];
    }

    /** @return array{0: int, 1: string} */
    private function locationFit(EmployerJob $job, ?string $location): array
    {
        $wanted = array_values(array_filter(array_map(fn ($l) => mb_strtolower(trim((string) $l)), $job->locations ?? [])));
        if ($job->remote || $wanted === []) {
            return [100, $location ?: 'Remote / any location'];
        }
        if ($location === null || trim($location) === '') {
            return [50, 'Location not given'];
        }

        $here = mb_strtolower($location);
        foreach ($wanted as $city) {
            if (str_contains($here, $city) || str_contains($city, $here)) {
                return [100, "{$location} — matches"];
            }
        }

        return [20, "{$location} — different city"];
    }

    /** Whole-word mention, so "java" does not match "javascript". */
    private function mentions(string $blob, string $skill): bool
    {
        return (bool) preg_match('/(?<![a-z0-9])'.preg_quote($skill, '/').'(?![a-z0-9])/u', $blob);
    }

    /** @param  list<string>  $words */
    private function anyMention(string $blob, array $words): bool
    {
        foreach ($words as $word) {
            if ($this->mentions($blob, $word)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function roleWords(EmployerJob $job): array
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower($job->title.' '.($job->role_family ?? ''))) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $w) => mb_strlen($w) >= self::MIN_ROLE_WORD_LENGTH)));
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
