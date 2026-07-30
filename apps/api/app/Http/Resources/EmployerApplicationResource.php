<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\EmployerApplicationStage;
use App\Models\EmployerJobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employer-facing application row. Progressive disclosure (PRD-E §8.6):
 * candidate contact details are revealed only from Shortlisted onwards —
 * anti-harvesting.
 *
 * @mixin EmployerJobApplication
 */
final class EmployerApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $contactVisible = ! in_array($this->stage, [
            EmployerApplicationStage::Applied,
            EmployerApplicationStage::Graded,
        ], true);

        return [
            'id' => $this->id,
            'job_id' => $this->employer_job_id,
            'stage' => $this->stage->value,
            'mock_score' => $this->mock_score,
            'graded_at' => $this->graded_at?->toIso8601String(),
            'is_graded' => $this->graded_at !== null,
            'rejection_reason' => $this->rejection_reason,
            'knockout_answers' => $this->knockout_answers,
            'candidate' => $this->whenLoaded('candidate', fn (): array => [
                'id' => $this->candidate->id,
                'name' => $this->candidate->name,
                'email' => $contactVisible ? $this->candidate->email : null,
                'phone' => $contactVisible ? $this->candidate->phone : null,
            ]),
            'evidence' => $this->whenLoaded('mockInterview', fn (): ?array => $this->mockInterview === null ? null : [
                'mock_interview_id' => $this->mockInterview->id,
                'overall_score' => $this->mockInterview->overall_score,
                'scorecard' => $this->mockInterview->scorecard,
            ]),
            'timeline' => $this->whenLoaded('transitions', fn (): array => $this->transitions->map(fn ($t): array => [
                'from' => $t->from_stage,
                'to' => $t->to_stage,
                'actor_type' => $t->actor_type,
                'note' => $t->note,
                'occurred_at' => $t->occurred_at->toIso8601String(),
            ])->all()),
            'applied_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
