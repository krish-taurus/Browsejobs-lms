<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketAuthorType: string
{
    case Student = 'student';
    case Staff = 'staff';
    case System = 'system';
}
