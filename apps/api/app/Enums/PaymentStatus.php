<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Created = 'created';
    case Captured = 'captured';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
