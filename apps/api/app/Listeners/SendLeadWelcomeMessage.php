<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MessageChannel;
use App\Events\LeadCaptured;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends a WhatsApp confirmation when a lead is captured (PRD §14.6 "CRM POST +
 * WhatsApp confirmation"; the LeadCaptured "P2.4 messaging" seam). The repo's
 * first queued listener — the event-driven path CLAUDE.md prefers for
 * automations. Idempotent enough: a duplicate capture is a separate lead.
 */
final class SendLeadWelcomeMessage implements ShouldQueue
{
    public function __construct(private readonly Messenger $messenger) {}

    public function handle(LeadCaptured $event): void
    {
        $lead = $event->lead;
        if ($lead->phone === null || $lead->phone === '') {
            return;
        }

        $tenant = $lead->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($lead): void {
            $this->messenger->sendRaw(
                $lead->tenant_id,
                MessageChannel::WhatsApp,
                $lead->phone,
                'lead_welcome',
                ['name' => $lead->name, 'link' => ''],
                $lead,
            );
        });
    }
}
