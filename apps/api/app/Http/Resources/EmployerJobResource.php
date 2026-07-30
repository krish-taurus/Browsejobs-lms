<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployerJob */
final class EmployerJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->employer_workspace_id,
            'title' => $this->title,
            'description' => $this->description,
            'role_family' => $this->role_family,
            'skills' => $this->skills,
            'experience_min_years' => $this->experience_min_years,
            'experience_max_years' => $this->experience_max_years,
            'locations' => $this->locations,
            'remote' => $this->remote,
            'ctc_min_paise' => $this->ctc_min_paise,
            'ctc_max_paise' => $this->ctc_max_paise,
            'ctc_visible' => $this->ctc_visible,
            'openings' => $this->openings,
            'knockout_questions' => $this->knockout_questions,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'current_mock' => $this->whenLoaded('mocks', function (): ?array {
                $mock = $this->currentMock();

                return $mock === null ? null : [
                    'id' => $mock->id,
                    'version' => $mock->version,
                    'status' => $mock->status->value,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
