<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assignments\GradeSubmission;
use App\Models\AssignmentSubmission;
use Illuminate\Console\Command;

/**
 * Runs the AI rubric grading for one submission, producing the DRAFT grade a
 * trainer then reviews and releases. Driven by the CRM's Grading page for
 * submissions the automatic pipeline has not graded yet.
 */
final class GradeAssignmentSubmission extends Command
{
    protected $signature = 'grading:grade {submission : Assignment submission id}';

    protected $description = 'AI-grade a submission against its rubric (draft, pending release)';

    public function handle(GradeSubmission $grade): int
    {
        $submission = AssignmentSubmission::query()->withoutGlobalScopes()->find((int) $this->argument('submission'));

        if ($submission === null) {
            $this->error('Submission not found.');

            return self::FAILURE;
        }

        $result = $grade->handle($submission);

        if ($result === null) {
            $this->warn('Submission already has a released grade — not re-grading.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'AI draft ready: %d/%d — review and release it when you are happy.',
            (int) $result->score,
            (int) $result->max_points,
        ));

        return self::SUCCESS;
    }
}
