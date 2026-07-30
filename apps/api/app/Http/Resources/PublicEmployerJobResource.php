<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Candidate-facing published JD (PRD-E F3). CTC only when the employer
 * opted in; knockout questions shown so the candidate can answer at
 * apply time.
 *
 * @mixin EmployerJob
 */
final class PublicEmployerJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'role_family' => $this->role_family,
            'skills' => $this->skills,
            'experience_min_years' => $this->experience_min_years,
            'experience_max_years' => $this->experience_max_years,
            'locations' => $this->locations,
            'remote' => $this->remote,
            'ctc_min_paise' => $this->when($this->ctc_visible, $this->ctc_min_paise),
            'ctc_max_paise' => $this->when($this->ctc_visible, $this->ctc_max_paise),
            'openings' => $this->openings,
            'knockout_questions' => $this->knockout_questions,
            'company' => $this->whenLoaded('workspace', fn (): array => [
                'name' => $this->workspace->name,
                'industry' => $this->workspace->industry,
                'company_size' => $this->workspace->company_size,
            ]),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
