<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Taurus console is the owner's alone (ADR 0052). Deliberately not a
 * permission check: a permission like `manage-settings` can be granted to any
 * role later, and that must never open the brain keys or the approvals.
 *
 * Requires the super-admin role and, when `taurus.owner_emails` is set, an
 * email on that list (case-insensitive).
 */
final class EnsureTaurusOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole('super-admin') || ! $this->allowedEmail((string) $user->email)) {
            return response()->json(['error' => [
                'code' => 'forbidden',
                'message' => 'Taurus is available to the platform owner only.',
            ]], 403);
        }

        return $next($request);
    }

    private function allowedEmail(string $email): bool
    {
        $owners = self::ownerEmails();

        return $owners === [] || in_array(mb_strtolower(trim($email)), $owners, true);
    }

    /** @return list<string> */
    public static function ownerEmails(): array
    {
        $raw = config('taurus.owner_emails', '');
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(array_map(
            static fn ($email): string => mb_strtolower(trim((string) $email)),
            $list,
        ), static fn (string $email): bool => $email !== ''));
    }
}
