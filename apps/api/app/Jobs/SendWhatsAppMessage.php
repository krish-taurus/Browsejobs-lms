<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Delivers a queued WhatsApp message (PRD §6.9). Idempotent: only sends a message
 * still in the queued state. Failures are logged on the message, not thrown.
 */
final class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $messageId) {}

    public function handle(WhatsAppClient $client): void
    {
        $message = Message::query()->withoutGlobalScopes()->find($this->messageId);
        if ($message === null || $message->status !== MessageStatus::Queued || $message->recipient === null) {
            return;
        }

        try {
            $template = $message->meta['wa_template'] ?? null;
            $image = $message->meta['wa_image'] ?? null;

            // Image card (banner + caption) while no approved template covers
            // this key; approved templates win once activated.
            $providerId = $template === null && $image !== null
                ? $client->sendImage($message->recipient, $image, (string) $message->body)
                : $client->sendMessage(
                    $message->recipient,
                    (string) $message->body,
                    $template,
                    $message->meta['wa_params'] ?? [],
                );

            $message->update([
                'status' => MessageStatus::Sent->value,
                'sent_at' => now(),
                'provider_message_id' => $providerId,
            ]);
        } catch (Throwable $e) {
            $message->update([
                'status' => MessageStatus::Failed->value,
                'failed_reason' => mb_substr($e->getMessage(), 0, 250),
            ]);
        }
    }
}
