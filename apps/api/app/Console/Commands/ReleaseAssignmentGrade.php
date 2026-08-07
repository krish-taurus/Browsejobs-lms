<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assignments\ReleaseGrade;
use App\Enums\GradeStatus;
use App\Models\AssignmentGrade;
use Illuminate\Console\Command;

/**
 * Releases a draft grade to the student — the PRD §7 human checkpoint, driven
 * from the CRM's Grading page. Runs the same ReleaseGrade action as the LMS
 * admin queue (audit + submission flip + student notification).
 */
final class ReleaseAssignmentGrade extends Command
{
    protected $signature = 'grading:release {grade : Assignment grade id}';

    protected $description = 'Release a draft assignment grade to the student';

    public function handle(ReleaseGrade $release): int
    {
        $grade = AssignmentGrade::query()->withoutGlobalScopes()->find((int) $this->argument('grade'));

        if ($grade === null) {
            $this->error('Grade not found.');

            return self::FAILURE;
        }

        if ($grade->status === GradeStatus::Approved) {
            $this->warn('Grade is already released.');

            return self::SUCCESS;
        }

        $grade = $release->handle($grade);

        $student = $grade->submission?->student;
        $this->info(sprintf(
            'Released %d/%d to %s — the student has been notified.',
            (int) $grade->score,
            (int) $grade->max_points,
            $student?->name ?? 'student',
        ));

        return self::SUCCESS;
    }
}
