<?php

declare(strict_types=1);

namespace App\Actions\Assignments;

use App\Enums\AiPurpose;
use App\Enums\BatchMemberStatus;
use App\Enums\GradeStatus;
use App\Models\AiEvent;
use App\Models\Assignment;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;

/**
 * Grades a submission against its rubric via the AI gateway (PRD §6.5), producing a
 * DRAFT grade the trainer releases. Billed to the student's batch trainer (grading is
 * a staff function), not the student's interactive budget. Robustly parses the JSON,
 * clamps each criterion score to its rubric max, and sums the overall score.
 */
final readonly class GradeSubmission
{
    public function __construct(private AiGateway $gateway) {}

    public function handle(AssignmentSubmission $submission): ?AssignmentGrade
    {
        return app(TenantContext::class)->run($submission->tenant, function () use ($submission): ?AssignmentGrade {
            if ($submission->hasReleasedGrade()) {
                return null; // never re-grade a released submission
            }

            $assignment = $submission->assignment;
            $module = $assignment->lesson?->topic?->module;
            $billing = $this->billingUser($submission);

            $result = $this->gateway->complete(
                $billing,
                AiPurpose::Grading,
                'grading',
                (int) config('assignments.grading.prompt_version', 1),
                [
                    'course' => $module?->course?->name ?? '',
                    'module' => $module?->name ?? '',
                    'assignment_title' => $assignment->title,
                    'instructions' => (string) $assignment->instructions,
                    'rubric' => json_encode($assignment->rubric ?? [], JSON_PRETTY_PRINT) ?: '[]',
                    'submission_body' => (string) $submission->body,
                    'submission_link' => (string) $submission->link,
                ],
                ['max_tokens' => (int) config('assignments.grading.max_tokens', 1200)],
            );

            $parsed = $this->parse($result->text);
            if ($parsed === null) {
                return null; // unparseable → leave for manual grading
            }

            [$criteria, $score] = $this->scoreCriteria($assignment, $parsed['criteria']);

            $aiEventId = AiEvent::query()
                ->where('user_id', $billing->id)
                ->where('purpose', AiPurpose::Grading->value)
                ->latest('id')
                ->value('id');

            return AssignmentGrade::query()->updateOrCreate(
                ['submission_id' => $submission->id],
                [
                    'tenant_id' => $submission->tenant_id,
                    'status' => GradeStatus::Draft->value,
                    'score' => $score,
                    'max_points' => $assignment->max_points,
                    'feedback' => $parsed['feedback'],
                    'criteria' => $criteria,
                    'ai_likelihood' => $parsed['ai_likelihood'],
                    'ai_event_id' => $aiEventId,
                ],
            );
        });
    }

    /** The batch trainer for the assignment's course, else the student (fallback). */
    private function billingUser(AssignmentSubmission $submission): User
    {
        $courseId = $submission->assignment->lesson?->topic?->module?->course_id;
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $batch = Batch::query()
            ->whereNotNull('trainer_id')
            ->where('course_id', $courseId)
            ->whereHas('members', fn ($q) => $q->where('user_id', $submission->user_id)->whereIn('status', $occupying))
            ->orderByDesc('id')
            ->first();

        return $batch?->trainer ?? $submission->student;
    }

    /**
     * Clamp each criterion score to its rubric max (0-floor) and sum the total.
     *
     * @param  list<array{criterion: string, score: mixed, note?: mixed}>  $rows
     * @return array{0: list<array{criterion: string, score: int, note: string}>, 1: int}
     */
    private function scoreCriteria(Assignment $assignment, array $rows): array
    {
        $criteria = [];
        $total = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['criterion'] ?? ''));
            if ($name === '') {
                continue;
            }
            $score = max(0, min((int) ($row['score'] ?? 0), $assignment->rubricMaxFor($name)));
            $total += $score;

            $criteria[] = ['criterion' => $name, 'score' => $score, 'note' => trim((string) ($row['note'] ?? ''))];
        }

        return [$criteria, min($total, $assignment->max_points)];
    }

    /**
     * @return array{feedback: string, criteria: list<array<string, mixed>>, ai_likelihood: int}|null
     */
    private function parse(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded) || ! isset($decoded['criteria']) || ! is_array($decoded['criteria'])) {
            return null;
        }

        return [
            'feedback' => trim((string) ($decoded['feedback'] ?? '')),
            'criteria' => array_values(array_filter($decoded['criteria'], 'is_array')),
            'ai_likelihood' => max(0, min(100, (int) ($decoded['ai_likelihood'] ?? 0))),
        ];
    }
}
