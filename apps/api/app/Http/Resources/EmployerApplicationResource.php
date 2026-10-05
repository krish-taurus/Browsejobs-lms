<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\EmployerApplicationStage;
use App\Models\EmployerJobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
                'recording_url' => $this->recordingUrl($this->mockInterview->recording_url),
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

    /**
     * `recording_url` on the interview is either a full URL already (a
     * future Vapi-hosted recording) or our own S3 key (the room's
     * webcam+mic upload) — signed here so the raw bucket path never
     * reaches the browser. Null on any storage hiccup rather than a
     * broken evidence panel.
     */
    private function recordingUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }
        try {
            return Storage::disk('s3')->temporaryUrl($stored, now()->addMinutes(30));
        } catch (Throwable) {
            return null;
        }
    }
}
