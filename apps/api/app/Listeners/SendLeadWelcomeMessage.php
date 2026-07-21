<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MessageChannel;
use App\Events\LeadCaptured;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends the masterclass welcome when a lead is captured (PRD §14.6 "CRM POST +
 * WhatsApp confirmation"; the LeadCaptured "P2.4 messaging" seam). The repo's
 * first queued listener — the event-driven path CLAUDE.md prefers for
 * automations. Delivers on WhatsApp AND email, each carrying a live link to the
 * masterclass page (every marketing CTA funnels to Book Free Masterclass), so a
 * captured lead is never a dead end. Idempotent enough: a duplicate capture is a
 * separate lead.
 */
final class SendLeadWelcomeMessage implements ShouldQueue
{
    public function __construct(private readonly Messenger $messenger) {}

    public function handle(LeadCaptured $event): void
    {
        $lead = $event->lead;

        $tenant = $lead->tenant;
        if ($tenant === null) {
            return;
        }

        $vars = ['name' => $lead->name, 'link' => $this->masterclassLink()];

        app(TenantContext::class)->run($tenant, function () use ($lead, $vars): void {
            if ($lead->phone !== null && $lead->phone !== '') {
                $this->messenger->sendRaw($lead->tenant_id, MessageChannel::WhatsApp, $lead->phone, 'lead_welcome', $vars, $lead);
            }

            if ($lead->email !== null && $lead->email !== '') {
                $this->messenger->sendRaw($lead->tenant_id, MessageChannel::Email, $lead->email, 'lead_welcome', $vars, $lead);
            }
        });
    }

    /** The public masterclass page — where the lead books/joins the next free session. */
    private function masterclassLink(): string
    {
        return rtrim((string) config('app.frontend_url', ''), '/').'/masterclass';
    }
}
