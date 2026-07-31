<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Enums\MessageChannel;
use App\Models\Batch;
use App\Models\User;
use App\Support\Messaging\Messenger;

/**
 * Welcomes a student into a funnel batch on WhatsApp + email: batch number,
 * start date and the dashboard link (students sign in with an OTP to their
 * phone/email — no password exists to send). Used when the automation seats
 * someone in a masterclass and again when they roll into the bootcamp.
 */
final readonly class SendBatchWelcome
{
    public function __construct(private Messenger $messenger) {}

    public function handle(User $student, Batch $batch, ?string $startsLabel = null): void
    {
        $vars = [
            'name' => $student->name,
            'batch' => $batch->number,
            'starts' => $startsLabel ?? ($batch->starts_on ? $batch->starts_on->format('D, d M Y') : 'soon'),
        ];

        // {{link}} becomes a single-use magic LOGIN link straight into the
        // dashboard — the passwordless equivalent of "here are your credentials".
        $magic = [
            'action' => 'batch.welcome',
            'payload' => ['redirect' => rtrim((string) config('app.frontend_url', ''), '/').'/dashboard'],
        ];

        foreach ([MessageChannel::WhatsApp, MessageChannel::Email] as $channel) {
            $this->messenger->send($student, 'batch_welcome', $vars, ['channel' => $channel, 'magic' => $magic]);
        }
    }
}
