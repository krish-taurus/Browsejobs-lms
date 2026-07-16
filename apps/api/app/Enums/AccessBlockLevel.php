<?php

declare(strict_types=1);

namespace App\Enums;

enum AccessBlockLevel: string
{
    case Soft = 'soft';   // live classes + recordings locked
    case Hard = 'hard';   // fee screen only + counselor task
}
