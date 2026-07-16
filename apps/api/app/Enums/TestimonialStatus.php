<?php

declare(strict_types=1);

namespace App\Enums;

enum TestimonialStatus: string
{
    case Pending = 'pending';   // awaiting admin moderation
    case Approved = 'approved'; // published + voucher issued
    case Rejected = 'rejected'; // not published, no voucher
}
