<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Models\User;

/**
 * Web push sender (PRD §6.9). Deferred in P2.4 — bound to {@see NullPushSender}
 * until VAPID keys + the service worker + browser subscriptions land.
 */
interface PushSender
{
    public function push(User $user, string $title, string $body, ?string $url): void;
}
