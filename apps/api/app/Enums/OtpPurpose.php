<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case StaffTwoFactor = 'staff_2fa';
}
