<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherType: string
{
    case Flat = 'flat';       // value = discount in paise
    case Percent = 'percent'; // value = basis points (e.g. 1000 = 10%)
}
