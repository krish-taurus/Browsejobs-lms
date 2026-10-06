<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * A same-site path to send someone after sign-in. Rejects absolute URLs,
 * protocol-relative paths, and anything that is not a single site path.
 */
final class SafeNextPath
{
    public static function check(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $path = trim($value);
        if ($path === '' || strlen($path) > 200) {
            return null;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return null;
        }

        if (preg_match('/[\s\\\\]/', $path) === 1 || str_contains($path, '://')) {
            return null;
        }

        return $path;
    }
}
