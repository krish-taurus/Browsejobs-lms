<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Actions\Crm\RecordTimelineEvent;
use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\LeadReplied;
use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\Message;
use App\Support\Crm\PhoneNormalizer;

/**
 * Records an inbound WhatsApp message (PRD §6.9 two-way). Logs it, correlates it
 * to a lead by normalized phone, lands a `message_in` on the CRM timeline, stamps
 * `leads.last_replied_at`, and fires LeadReplied (the P2.5 auto-pause seam).
 * Runs inside the tenant context set by the webhook.
 */
final readonly class RecordInboundMessage
{
    public function __construct(private RecordTimelineEvent $timeline) {}

    public function handle(int $tenantId, string $from, string $body, ?string $providerMessageId = null): Message
    {
        $lead = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('phone_normalized', PhoneNormalizer::normalize($from))
            ->whereNull('merged_into_id')
            ->orderBy('id')
            ->first();

        $message = Message::query()->create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead?->id,
            'direction' => MessageDirection::In->value,
            'channel' => MessageChannel::WhatsApp->value,
            'category' => MessageCategory::Utility->value,
            'recipient' => $from,
            'body' => $body,
            'status' => MessageStatus::Delivered->value,
            'provider_message_id' => $providerMessageId,
        ]);

        if ($lead !== null) {
            $this->timeline->handle($lead, ContactTimelineEvent::TYPE_MESSAGE_IN, $body, ['from' => $from]);
            $lead->forceFill(['last_replied_at' => now()])->save();
            LeadReplied::dispatch($lead, $body);
        }

        return $message;
    }
}
