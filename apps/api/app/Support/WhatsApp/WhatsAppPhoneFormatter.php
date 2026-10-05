<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use App\Support\Crm\PhoneNormalizer;

/**
 * The Cloud API's `to` field must be the full international number (country
 * code + subscriber number, digits only). A bare 10-digit Indian mobile is
 * NOT rejected by the API — it still returns a message id, so the send is
 * logged as "sent" — but WhatsApp has no country to route it under, so the
 * message never reaches a real device. This platform is India-only today,
 * so a 10-digit number is unambiguously missing the 91 country code;
 * anything else already carries one and is left untouched rather than
 * guessed at.
 */
final class WhatsAppPhoneFormatter
{
    public static function toApiFormat(string $phone): string
    {
        $digits = PhoneNormalizer::normalize($phone);

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }
}
