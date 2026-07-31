<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employer view of a round: full evidence — answers, per-dimension
 * scores, recruiter summary (PRD-E F5).
 *
 * @mixin EmployerInterview
 */
final class EmployerInterviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->employer_job_application_id,
            'round' => $this->round,
            // Snapshotted at invite time, so a later rename does not rewrite
            // what the candidate was told they were sitting.
            'round_name' => $this->round_name,
            'status' => $this->status->value,
            'question_set' => $this->question_set,
            'answers' => $this->answers,
            'dimension_scores' => $this->dimension_scores,
            'overall_score' => $this->overall_score,
            'grading_summary' => $this->grading_summary,
            'grading_delayed' => $this->status->value === 'submitted'
                && $this->submitted_at !== null
                && $this->submitted_at->diffInMinutes(now()) > 60,
            'invited_at' => $this->invited_at->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'graded_at' => $this->graded_at?->toIso8601String(),
        ];
    }
}
