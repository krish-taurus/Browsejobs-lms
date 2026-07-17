<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a student did with an AI first-response (PRD §6.13). The accept rate is the
 * deflection rate the PRD puts at 30–50%, measured rather than assumed.
 */
enum DeflectionOutcome: string
{
    /** An answer was shown; the student has not chosen yet (or abandoned the form). */
    case Offered = 'offered';

    /** The answer resolved it — no ticket was created. */
    case Accepted = 'accepted';

    /** The answer did not resolve it — the student raised the ticket anyway. */
    case Proceeded = 'proceeded';
}
