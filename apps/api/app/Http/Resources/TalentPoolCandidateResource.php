<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trained BrowseJobs student surfaced as an eligible candidate for a
 * JD (PRD-E F7). Contact details are withheld — the employer invites
 * them to apply, and contact unlocks at Shortlisted like any other
 * candidate (progressive disclosure, PRD-E §8.6).
 *
 * @property array<string, mixed> $resource
 */
final class TalentPoolCandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
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
        ];
    }
}
