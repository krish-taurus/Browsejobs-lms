<?php

declare(strict_types=1);

namespace App\Support\MagicLink;

use App\Models\MagicLink;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Mints magic links. Every link is single-use, scoped to one action, and
 * expiring; the returned URL is a Laravel signed route so the token cannot be
 * tampered with. Auth/payment actions are capped at a 15-minute TTL per
 * CLAUDE.md; everything else defaults to 24 hours.
 */
final class MagicLinkService
{
    private const DEFAULT_TTL_MINUTES = 1440;

    private const SENSITIVE_TTL_MINUTES = 15;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(
        Tenant $tenant,
        string $action,
        ?User $user = null,
        array $payload = [],
        ?int $ttlMinutes = null,
    ): string {
        $ttl = min($ttlMinutes ?? self::DEFAULT_TTL_MINUTES, $this->maxTtlFor($action));
        $expiresAt = now()->addMinutes($ttl);

        $token = Str::random(48);

        MagicLink::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'action' => $action,
            'payload' => $payload,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        return URL::temporarySignedRoute('magic.consume', $expiresAt, ['token' => $token]);
    }

    private function maxTtlFor(string $action): int
    {
        $sensitive = Str::startsWith($action, ['pay.', 'auth.']);

        return $sensitive ? self::SENSITIVE_TTL_MINUTES : self::DEFAULT_TTL_MINUTES;
    }
}
