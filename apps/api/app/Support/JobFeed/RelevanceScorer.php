<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Models\CvProfile;
use App\Models\EmployerJob;
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
 *
 * Also scores employer JD postings (`scoreForEmployerJob`) — a different
 * source of skills/role-title (the employer's own posting, not a scraped
 * feed item) but the same underlying "how much overlap" question, so it
 * shares this class rather than duplicating skillBlob/roleBonus.
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
        return $this->scoreSkills(
            $student,
            $item->extracted_skills ?? [],
            (string) ($item->role_title ?? $item->title),
        );
    }

    /**
     * Same scoring, against an employer's own JD (PRD-E) rather than a
     * scraped feed item — used to gate auto-shortlisting on CV fit, not just
     * interview performance (Aug 2026 candidate request).
     *
     * @return array{match_pct: int, matched: list<string>, gap: list<string>}
     */
    public function scoreForEmployerJob(User $student, EmployerJob $job): array
    {
        return $this->scoreSkills($student, $job->skills ?? [], $job->title);
    }

    /**
     * @param  array<int, mixed>  $rawSkills
     * @return array{match_pct: int, matched: list<string>, gap: list<string>}
     */
    private function scoreSkills(User $student, array $rawSkills, string $roleTitle): array
    {
        $blob = $this->skillBlob($student);
        $skills = array_values(array_unique(array_map('strval', $rawSkills)));

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
        $roleBonus = $this->roleBonus($blob, $roleTitle);

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
