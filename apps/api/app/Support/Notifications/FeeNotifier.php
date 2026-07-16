<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\AccessBlock;
use App\Models\Instalment;
use App\Models\User;

/**
 * Delivery channel for dunning notifications (PRD §6.8). A separate seam from
 * SessionNotifier (which is LiveSession-typed). Logs locally until the P2.4
 * messaging hub binds a real WhatsApp/email implementation.
 */
interface FeeNotifier
{
    public function reminder(User $student, Instalment $instalment, string $rung, ?string $payUrl): void;

    public function blocked(User $student, AccessBlock $block): void;

    public function unblocked(User $student): void;
}
