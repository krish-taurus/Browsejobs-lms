<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductKind: string
{
    case Pack = 'pack';                 // grants credits to a wallet
    case Subscription = 'subscription'; // Career+ (recurring)
    case SelfPaced = 'self_paced';      // recorded-course access
    case Upgrade = 'upgrade';           // self-paced → live, pay the difference
}
