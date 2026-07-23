<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A metered feature backed by a countable credit wallet (PRD §6.17).
 */
enum EntitlementFeature: string
{
    case Cv = 'cv';
    case VoiceMock = 'voice_mock';
    case Mentor = 'mentor';
    case JobKit = 'job_kit';
}
