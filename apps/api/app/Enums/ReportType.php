<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of AI-narrated report (PRD §6.10).
 */
enum ReportType: string
{
    case WeeklyStudent = 'weekly_student';
    case TrainerBrief = 'trainer_brief';
    case CounselorDigest = 'counselor_digest';
    case SupportThemes = 'support_themes';

    /** The versioned prompt file name for this report's narration. */
    public function prompt(): string
    {
        return match ($this) {
            self::WeeklyStudent => 'report',
            self::TrainerBrief => 'trainer_brief',
            self::CounselorDigest => 'counselor_digest',
            self::SupportThemes => 'support_themes',
        };
    }
}
