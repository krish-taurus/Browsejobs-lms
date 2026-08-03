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
     * @param  string  $body  the rendered body (also the template body-param when templated and no $parameters given)
     * @param  string|null  $templateName  Meta-approved template name, or null for a session text
     * @param  list<string>  $parameters  ordered {{1}}, {{2}}, … values; falls back to [$body]
     * @param  bool  $authTemplate  AUTHENTICATION template — repeat the code in the copy-code button
     */
    public function sendMessage(
        string $to,
        string $body,
        ?string $templateName = null,
        array $parameters = [],
        bool $authTemplate = false,
    ): string;
}
