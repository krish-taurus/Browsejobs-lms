<?php

declare(strict_types=1);

namespace App\Actions\Assignments;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\GradeStatus;
use App\Models\AssignmentGrade;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;

/**
 * Trainer releases a grade to the student (PRD §6.5 / §7 human checkpoint). Flips the
 * grade to approved, marks the submission graded, audits it (grade change), and
 * notifies the student. Idempotent. Mirrors ApproveQuiz.
 */
final readonly class ReleaseGrade
{
    public function __construct(
        private AuditLogger $audit,
        private Messenger $messenger,
    ) {}

    public function handle(AssignmentGrade $grade, ?User $actor = null): AssignmentGrade
    {
        return app(TenantContext::class)->run($grade->tenant, function () use ($grade, $actor): AssignmentGrade {
            if ($grade->status === GradeStatus::Approved) {
                return $grade;
            }

            $grade->update([
                'status' => GradeStatus::Approved->value,
                'graded_by' => $actor?->id,
                'approved_at' => now(),
            ]);

            $submission = $grade->submission;
            $submission->update(['status' => AssignmentSubmissionStatus::Graded->value]);

            $this->audit->log(
                action: 'grade.released',
                target: $grade,
                metadata: ['submission_id' => $submission->id, 'score' => $grade->score, 'max_points' => $grade->max_points],
                actor: $actor,
            );

            $student = $submission->student;
            $assignment = $submission->assignment;
            $redirect = rtrim((string) config('app.frontend_url', ''), '/').'/assignments/'.$assignment->lesson_id;

            $this->messenger->send($student, 'grade_ready', [
                'name' => $student->name,
                'assignment' => $assignment->title,
                'score' => (string) $grade->score,
                'max' => (string) $grade->max_points,
            ], [
                'magic' => [
                    'action' => 'assignment.grade',
                    'payload' => ['assignment_id' => $assignment->id, 'redirect' => $redirect],
                ],
            ]);

            return $grade->refresh();
        });
    }
}
