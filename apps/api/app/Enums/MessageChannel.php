<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageChannel: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case InApp = 'inapp';
}
