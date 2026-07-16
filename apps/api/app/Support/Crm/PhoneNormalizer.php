<?php

declare(strict_types=1);

namespace App\Support\Crm;

/**
 * Normalizes phone numbers to a bare digit string so dedupe compares
 * `+91 86185 19825`, `+918618519825`, and `08618519825` consistently within a
 * tenant. Kept deliberately simple (no country-code inference) for P2.1.
 */
final class PhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
