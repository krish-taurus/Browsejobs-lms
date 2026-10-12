<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\JobShortlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person on a JD's AI shortlist. A client/Naukri candidate is the
 * client's own record, so their contact details are shown; a BrowseJobs
 * student's stay withheld here, as everywhere else before the client acts.
 *
 * @mixin JobShortlistEntry
 */
final class JobShortlistEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $external = $this->employerCandidate;

        return [
            'id' => $this->id,
            'rank' => $this->rank,
            'source' => $this->source,
            'match_score' => $this->match_score,
            'status' => $this->status,
            'reasons' => $this->reasons,
            'candidate_user_id' => $this->candidate_user_id,
            'employer_candidate_id' => $this->employer_candidate_id,
            'name' => $external?->name ?? $this->candidateUser?->name ?? 'Candidate',
            'current_title' => $external?->current_title,
            'current_company' => $external?->current_company,
            'location' => $external?->location,
            'experience_years' => $external?->experience_years !== null ? (float) $external->experience_years : null,
            'notice_period' => $external?->notice_period,
            'expected_ctc' => $external?->expected_ctc,
            'email' => $external?->email,
            'phone' => $external?->phone,
            'removed_at' => $this->removed_at?->toIso8601String(),
        ];
    }
}
