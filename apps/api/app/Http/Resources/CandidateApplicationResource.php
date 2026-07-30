<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerJobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Candidate-facing view of their own application (PRD-E F3). Never
 * exposes employer-internal notes; rejection reason is shown (it is
 * written candidate-respectfully by design).
 *
 * @mixin EmployerJobApplication
 */
final class CandidateApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage->value,
            'mock_score' => $this->mock_score,
            'is_graded' => $this->graded_at !== null,
            'rejection_reason' => $this->rejection_reason,
            'job' => $this->whenLoaded('job', fn (): array => [
                'id' => $this->job->id,
                'title' => $this->job->title,
                'locations' => $this->job->locations,
                'remote' => $this->job->remote,
                'status' => $this->job->status->value,
            ]),
            'applied_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
