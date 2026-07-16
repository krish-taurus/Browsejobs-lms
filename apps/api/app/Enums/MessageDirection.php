<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageDirection: string
{
    case Out = 'out';
    case In = 'in';
}
