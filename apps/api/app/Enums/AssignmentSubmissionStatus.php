<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of a student's assignment submission (PRD §6.5).
 */
enum AssignmentSubmissionStatus: string
{
    case Submitted = 'submitted';   // awaiting an AI/manual grade
    case Grading = 'grading';       // AI grading in flight
    case Graded = 'graded';         // a grade has been released to the student
}
