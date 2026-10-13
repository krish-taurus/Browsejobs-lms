<?php

declare(strict_types=1);

namespace App\Support\Crm;

/**
 * Normalizes phone numbers to a bare digit string so dedupe compares a
 * spaced display form, E.164, and a leading-zero national form of the same
 * number. Published lines live in config/contact.php. Kept deliberately
 * simple (no country-code inference) for P2.1.
 */
final class PhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
