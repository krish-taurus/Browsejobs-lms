<?php

declare(strict_types=1);

namespace App\Enums;

enum FeePlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
