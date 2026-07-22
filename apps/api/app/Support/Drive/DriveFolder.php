<?php

declare(strict_types=1);

namespace App\Support\Drive;

/**
 * Normalises whatever an admin pastes into the "Reviews folder link" setting into
 * a Google Drive folder ID — so a full share URL, a `?usp=sharing` link, or a bare
 * ID all resolve the same way, and the link can be changed without a deploy.
 */
final class DriveFolder
{
    /** Extract the folder ID from a Drive URL or a bare ID; null if none found. */
    public static function id(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // …/folders/{id}   or   ?id={id}  / &id={id}
        if (preg_match('~/folders/([A-Za-z0-9_-]+)~', $value, $m)
            || preg_match('~[?&]id=([A-Za-z0-9_-]+)~', $value, $m)) {
            return $m[1];
        }

        // A bare ID (no scheme, no path) — Drive IDs are URL-safe base64-ish.
        if (preg_match('~^[A-Za-z0-9_-]{10,}$~', $value)) {
            return $value;
        }

        return null;
    }
}
