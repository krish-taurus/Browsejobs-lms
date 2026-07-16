<?php

declare(strict_types=1);

namespace App\Enums;

enum FeePlanType: string
{
    case Single = 'single';
    case Emi = 'emi';
}
