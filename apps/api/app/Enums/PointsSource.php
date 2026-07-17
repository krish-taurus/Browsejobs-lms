<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Verified flows that may mint points (PRD §6.16). Anti-gaming: every case is
 * awarded ONLY from a server-side action — there is no client-facing endpoint
 * that writes points.
 */
enum PointsSource: string
{
    case Attendance = 'attendance';
    case Punctuality = 'punctuality';
    case Quiz = 'quiz';
    case Lab = 'lab';
    case Streak = 'streak';
    case Mock = 'mock'; // reserved — hooks arrive with P4 mock interviews
}
