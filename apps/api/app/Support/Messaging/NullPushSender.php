<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Models\User;

/**
 * No-op web push sender (P2.4 defers web push). Real implementation binds later.
 */
final class NullPushSender implements PushSender
{
    public function push(User $user, string $title, string $body, ?string $url): void
    {
        // Web push is deferred; no-op.
    }
}
