<?php

declare(strict_types=1);

namespace App\Enums;

/** Lifecycle of one interview round (PRD-E F5). */
enum EmployerInterviewStatus: string
{
    case Invited = 'invited';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';
    case Expired = 'expired';
}
