<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewGraded;
use App\Events\MockCompleted;
use App\Models\EmployerInterview;
use App\Models\InterviewInvite;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * A spoken L1/L2 interview finished in the room: its scorecard becomes the
 * round's grade, and EmployerInterviewGraded carries on exactly as for a
 * typed round — automation rules (e.g. "L1 ≥ 75 → L2"), the next automatic
 * round, the pipeline. The recording stays on the mock interview.
 *
 * Mocks that are not a round (practice, the JD interview, the CV interview)
 * fall straight through.
 */
final class RecordRoundInterviewScore implements ShouldQueue
{
    public function handle(MockCompleted $event): void
    {
        $mock = $event->interview;
        if ($mock->blueprint?->employer_job_round_id === null || $mock->overall_score === null) {
            return;
        }

        $interview = EmployerInterview::withoutGlobalScopes()->where('mock_interview_id', $mock->id)->first();
        if ($interview === null || $interview->status === EmployerInterviewStatus::Graded) {
            return;
        }

        app(TenantContext::class)->run($interview->tenant, function () use ($interview, $mock): void {
            $card = is_array($mock->scorecard) ? $mock->scorecard : [];
            $dimensions = [];
            foreach ((array) ($card['competencies'] ?? []) as $row) {
                $name = is_array($row) ? ($row['name'] ?? $row['competency'] ?? null) : null;
                $score = is_array($row) ? ($row['score'] ?? null) : null;
                if (is_string($name) && is_numeric($score)) {
                    $dimensions[Str::slug($name, '_')] = max(0, min(100, (int) $score));
                }
            }

            $interview->update([
                'status' => EmployerInterviewStatus::Graded->value,
                'overall_score' => max(0, min(100, (int) $mock->overall_score)),
                'dimension_scores' => $dimensions !== [] ? $dimensions : $interview->dimension_scores,
                'grading_summary' => is_string($card['summary'] ?? null) ? $card['summary'] : (is_string($card['verdict'] ?? null) ? $card['verdict'] : null),
                'grading_source' => 'voice_interview',
                'submitted_at' => $interview->submitted_at ?? now(),
                'graded_at' => now(),
            ]);

            InterviewInvite::query()->where('employer_interview_id', $interview->id)->update(['status' => InterviewInvite::DONE]);

            EmployerInterviewGraded::dispatch($interview->fresh());
        });
    }
}
