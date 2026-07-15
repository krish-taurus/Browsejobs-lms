<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidMagicLinkException;
use App\Models\MagicLink;

/**
 * Validates and atomically consumes a magic link. The consume is a conditional
 * UPDATE guarded on `consumed_at IS NULL`, so a link can be redeemed exactly
 * once even under concurrent requests.
 */
final readonly class ConsumeMagicLink
{
    public function handle(string $token): MagicLink
    {
        $link = MagicLink::query()
            ->withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($link === null || $link->consumed_at !== null || $link->expires_at->isPast()) {
            throw new InvalidMagicLinkException;
        }

        $consumed = MagicLink::query()
            ->withoutGlobalScopes()
            ->whereKey($link->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($consumed === 0) {
            throw new InvalidMagicLinkException;
        }

        return $link->refresh();
    }
}
