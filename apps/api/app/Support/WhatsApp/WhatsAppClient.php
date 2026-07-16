<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

/**
 * WhatsApp Cloud API sender (PRD §6.9). Business-initiated messages use a
 * pre-approved template (Utility category); free text is only valid inside the
 * 24-hour customer service window. Called only from queued jobs; tests bind
 * {@see FakeWhatsAppClient}.
 */
interface WhatsAppClient
{
    /**
     * Send a message and return the provider message id (wamid).
     *
     * @param  string  $to  E.164 phone (digits)
     * @param  string  $body  the rendered body (also the template body-param when templated)
     * @param  string|null  $templateName  Meta-approved template name, or null for a session text
     */
    public function sendMessage(string $to, string $body, ?string $templateName = null): string;
}
