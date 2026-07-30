<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\EmployerInterviewStatus;
use App\Models\EmployerInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Candidate view of a round: questions are visible, but rubric,
 * dimension scores, and the recruiter summary never are — grading
 * detail is employer-facing evidence (PRD-E F5).
 *
 * @mixin EmployerInterview
 */
final class CandidateInterviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round' => $this->round->value,
            'status' => $this->status->value,
            'questions' => $this->when(
                in_array($this->status, [EmployerInterviewStatus::Invited, EmployerInterviewStatus::InProgress], true),
                fn (): array => array_map(
                    fn (array $q): array => ['id' => $q['id'] ?? null, 'text' => $q['text'] ?? ''],
                    $this->question_set,
                ),
            ),
            'job' => $this->whenLoaded('application', fn (): ?array => $this->application->job === null ? null : [
                'id' => $this->application->job->id,
                'title' => $this->application->job->title,
            ]),
            'invited_at' => $this->invited_at->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
