<?php

declare(strict_types=1);

namespace App\Enums;

enum TestimonialStatus: string
{
    case Pending = 'pending';   // awaiting admin moderation
    case Approved = 'approved'; // published + voucher issued
    case Rejected = 'rejected'; // not published, no voucher

    // A rating below the public threshold: kept private as feedback, never
    // published and never rewarded. The student sees a "thank you, we'll look
    // into it" acknowledgement instead of the Google-review hand-off.
    case PrivateFeedback = 'private_feedback';
}
