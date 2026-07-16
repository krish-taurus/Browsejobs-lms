<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageCategory: string
{
    case Utility = 'utility';     // transactional: OTP, payment, reminders — bypasses guards
    case Marketing = 'marketing'; // promotional: needs opt-in + respects quiet hours/caps
}
