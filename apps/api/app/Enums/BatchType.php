<?php

declare(strict_types=1);

namespace App\Enums;

enum BatchType: string
{
    case Masterclass = 'masterclass';
    case Bootcamp = 'bootcamp';
    case Paid = 'paid';
}
