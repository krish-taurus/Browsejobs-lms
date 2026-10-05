<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trained BrowseJobs student surfaced as an eligible candidate for a
 * JD (PRD-E F7). Contact details are withheld for a real candidate — the
 * employer invites them to apply, and contact unlocks at Shortlisted like
 * any other candidate (progressive disclosure, PRD-E §8.6). A synthetic
 * "sample" row (see SampleTalentMatcher, opt-in only) has no such
 * restriction — it isn't a real person's contact detail to protect — and
 * carries `is_sample: true` so the UI can badge it and never wire it into
 * a real messaging action.
 *
 * @property array<string, mixed> $resource
 */
final class TalentPoolCandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        if (isset($row['sample'])) {
            $sample = $row['sample'];

            return [
                'candidate_id' => -$sample->id, // negative: never collides with a real user id
                'name' => $sample->name,
                'match_score' => $row['match_score'],
                'skill_match_pct' => $row['skill_match_pct'],
                'matched_skills' => $row['matched_skills'],
                'missing_skills' => $row['missing_skills'],
                'readiness_index' => $sample->ai_readiness_score,
                'mock_average' => $sample->ai_readiness_score,
                'mock_attempts' => 1,
                'training' => ['course' => null, 'batch' => null],
                'cv_ready' => true,
                'cv_mock_score' => $sample->cv_mock_score,
                'bgb_verified' => $sample->bgb_verified,
                'is_sample' => true,
                'email' => $sample->email,
                'phone' => $sample->phone,
                'location' => $sample->location,
                'education' => $sample->education,
                'cv_summary' => $sample->cv_summary,
            ];
        }

        $user = $row['user'];

        return [
            'candidate_id' => $user->id,
            'name' => $user->name,
            'match_score' => $row['match_score'],
            'skill_match_pct' => $row['skill_match_pct'],
            'matched_skills' => $row['matched_skills'],
            'missing_skills' => $row['missing_skills'],
            'readiness_index' => $row['readiness_index'],
            'mock_average' => $row['mock_average'],
            'mock_attempts' => $row['mock_attempts'],
            'training' => $row['training'],
            'cv_ready' => $row['cv_ready'],
            // Every candidate here has one, by construction — LmsTalentMatcher
            // only includes students who completed the AI Readiness Interview.
            'cv_mock_score' => $row['cv_mock_score'],
            // Label only for now (no real check behind it yet) — same
            // badge a sample row gets, so a demo and the real product read
            // identically. Wire to a genuine check (e.g. PF account
            // presence) here once one exists.
            'bgb_verified' => true,
            'is_sample' => false,
        ];
    }
}
