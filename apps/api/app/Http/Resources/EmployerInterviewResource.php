<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Actions\Employers\BuildInterviewReport;
use App\Models\EmployerInterview;
use App\Models\EmployerInterviewReport;
use App\Models\MockIntegrityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            // What the interview room noticed, with the still taken then.
            'integrity' => $this->integrity(),
            // The PDF report sent to the client's WhatsApp after this round.
            'report_url' => $this->reportUrl(),
        ];
    }

    /** @return array{flags: int, possible: int, events: list<array<string, mixed>>}|null */
    private function integrity(): ?array
    {
        if ($this->mock_interview_id === null) {
            return null;
        }

        $disk = Storage::disk(BuildInterviewReport::DISK);
        $events = MockIntegrityEvent::query()
            ->where('mock_interview_id', $this->mock_interview_id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return [
            'flags' => $events->where('severity', MockIntegrityEvent::FLAG)->count(),
            'possible' => $events->where('severity', MockIntegrityEvent::POSSIBLE)->count(),
            'events' => $events->map(fn (MockIntegrityEvent $e): array => [
                'kind' => $e->kind,
                'label' => $e->label(),
                'severity' => $e->severity,
                'elapsed_seconds' => $e->elapsed_seconds,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                // Short-lived: the stills stay private in storage.
                'image_url' => $e->image_path !== null ? $disk->temporaryUrl($e->image_path, now()->addMinutes(30)) : null,
            ])->values()->all(),
        ];
    }

    private function reportUrl(): ?string
    {
        $path = EmployerInterviewReport::query()->where('employer_interview_id', $this->id)->value('pdf_path');

        return is_string($path) ? Storage::disk(BuildInterviewReport::DISK)->temporaryUrl($path, now()->addMinutes(30)) : null;
    }
}
