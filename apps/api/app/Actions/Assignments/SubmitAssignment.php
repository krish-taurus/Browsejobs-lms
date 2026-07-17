<?php

declare(strict_types=1);

namespace App\Actions\Assignments;

use App\Enums\AssignmentSubmissionStatus;
use App\Jobs\GradeSubmissionJob;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Records a student's submission (PRD §6.5) and queues AI grading. One active
 * submission per (assignment, student) — a resubmit replaces it and re-grades. A
 * submission with a released grade is locked.
 */
final readonly class SubmitAssignment
{
    public function __construct(private StoreSubmissionFile $storeFile) {}

    public function handle(User $student, Assignment $assignment, ?string $body, ?string $link, ?UploadedFile $file): AssignmentSubmission
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $assignment, $body, $link, $file): AssignmentSubmission {
            $existing = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $student->id)
                ->first();

            if ($existing !== null && $existing->hasReleasedGrade()) {
                throw ValidationException::withMessages(['submission' => 'This assignment has already been graded and cannot be resubmitted.']);
            }

            $attributes = [
                'tenant_id' => $student->tenant_id,
                'body' => $body,
                'link' => $assignment->allow_link ? $link : null,
                'status' => AssignmentSubmissionStatus::Submitted->value,
                'submitted_at' => now(),
            ];

            if ($file !== null && $assignment->allow_file) {
                $attributes = array_merge($attributes, $this->storeFile->handle($student, $file));
            }

            $submission = AssignmentSubmission::query()->updateOrCreate(
                ['assignment_id' => $assignment->id, 'user_id' => $student->id],
                $attributes,
            );

            // Re-grade from scratch on resubmit: drop the old draft grade.
            $submission->grade()->delete();

            GradeSubmissionJob::dispatch($submission->id);

            return $submission;
        });
    }
}
