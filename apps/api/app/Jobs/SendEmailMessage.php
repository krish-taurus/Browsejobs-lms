<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Mail\MessageMail;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers a queued email message (PRD §6.9). Idempotent; failures logged on the
 * message.
 */
final class SendEmailMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $messageId) {}

    public function handle(): void
    {
        $message = Message::query()->withoutGlobalScopes()->find($this->messageId);
        if ($message === null || $message->status !== MessageStatus::Queued || $message->recipient === null) {
            return;
        }

        try {
            Mail::to($message->recipient)->send(new MessageMail(
                $message->subject ?? 'BrowseJobs',
                (string) $message->body,
                $message->template_key,
            ));

            $message->update(['status' => MessageStatus::Sent->value, 'sent_at' => now()]);
        } catch (Throwable $e) {
            $message->update([
                'status' => MessageStatus::Failed->value,
                'failed_reason' => mb_substr($e->getMessage(), 0, 250),
            ]);
        }
    }
}
