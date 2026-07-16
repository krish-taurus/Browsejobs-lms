<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\AccessBlock;
use App\Models\Instalment;
use App\Models\User;
use App\Support\Messaging\Messenger;

/**
 * Routes dunning notifications through the messaging hub (P2.4). Fee reminders
 * carry the Razorpay payment link so the student can pay in one tap.
 */
final class MessengerFeeNotifier implements FeeNotifier
{
    public function __construct(private readonly Messenger $messenger) {}

    public function reminder(User $student, Instalment $instalment, string $rung, ?string $payUrl): void
    {
        $this->messenger->send($student, 'fee_reminder', [
            'name' => $student->name,
            'amount' => number_format($instalment->amount_paise / 100, 2),
            'link' => $payUrl ?? '',
        ]);
    }

    public function blocked(User $student, AccessBlock $block): void
    {
        $this->messenger->send($student, 'fee_blocked', [
            'name' => $student->name,
            'level' => $block->level->value,
        ]);
    }

    public function unblocked(User $student): void
    {
        $this->messenger->send($student, 'fee_unblocked', ['name' => $student->name]);
    }
}
