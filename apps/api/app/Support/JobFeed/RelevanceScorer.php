<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Models\CvProfile;
use App\Models\JobFeedItem;
use App\Models\StudentScore;
use App\Models\User;

/**
 * Scores a feed item's relevance to a student (PRD §6.22): how much of what the
 * job asks for the student already has, plus a role-fit bonus. Returns the match
 * percentage AND the "why" — which skills matched and which are the gap — so the
 * feed can explain itself ("matches your Python, SQL — gap: Kafka").
 *
 * Skills come from the student's own profile + the modules they've studied; no
 * per-student data leaves this scoring (it only reads the viewer's own record).
 */
final class RelevanceScorer
{
    /** @var array<int, string> cached skill blob per student for one request */
    private array $blobCache = [];

    /**
     * @return array{match_pct: int, matched: list<string>, gap: list<string>}
     */
    public function score(User $student, JobFeedItem $item): array
    {
        $blob = $this->skillBlob($student);
        $skills = array_values(array_unique(array_map('strval', $item->extracted_skills ?? [])));

        $matched = [];
        $gap = [];
        foreach ($skills as $skill) {
            $needle = mb_strtolower(trim($skill));
            if ($needle === '') {
                continue;
            }
            if ($blob !== '' && str_contains($blob, $needle)) {
                $matched[] = $skill;
            } else {
                $gap[] = $skill;
            }
        }

        $skillShare = $skills === [] ? 0.0 : count($matched) / count($skills);
        $roleBonus = $this->roleBonus($blob, (string) ($item->role_title ?? $item->title));

        // Skill overlap dominates; role fit nudges. Items with no extracted skills
        // yet score on role alone (they still surface, lower).
        $weightSkill = $skills === [] ? 0.0 : 0.75;
        $weightRole = 1.0 - $weightSkill;
        $matchPct = (int) round(100 * ($weightSkill * $skillShare + $weightRole * $roleBonus));

        return [
            'match_pct' => max(0, min(100, $matchPct)),
            'matched' => $matched,
            'gap' => $gap,
        ];
    }

    /** Fraction of the role title's significant words present in the student blob. */
    private function roleBonus(string $blob, string $roleTitle): float
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', mb_strtolower($roleTitle)) ?: [],
            fn ($w) => mb_strlen($w) >= 4,
        ));
        if ($words === [] || $blob === '') {
            return 0.0;
        }

        $hits = 0;
        foreach ($words as $word) {
            if (str_contains($blob, $word)) {
                $hits++;
            }
        }

        return $hits / count($words);
    }

    /** Lowercase text of everything the student knows: own skills + studied modules + courses. */
    private function skillBlob(User $student): string
    {
        if (isset($this->blobCache[$student->id])) {
            return $this->blobCache[$student->id];
        }

        $profile = CvProfile::dataFor($student);
        $ownSkills = array_map('strval', (array) ($profile['skills'] ?? []));

        $score = StudentScore::query()->where('user_id', $student->id)->first();
        $modules = collect($score?->mastery ?? [])->pluck('name')->map(fn ($n) => (string) $n)->all();

        $blob = mb_strtolower(implode(' ', [...$ownSkills, ...$modules]));

        return $this->blobCache[$student->id] = $blob;
    }
}
