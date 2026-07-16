<?php

declare(strict_types=1);

namespace App\Enums;

enum EnrolmentType: string
{
    case Live = 'live';
    case SelfPaced = 'self_paced';
}
