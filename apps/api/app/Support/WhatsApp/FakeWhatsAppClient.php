<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

/**
 * In-memory WhatsApp client for tests and local dev without WABA credentials.
 * Records sends and returns deterministic wamids.
 */
final class FakeWhatsAppClient implements WhatsAppClient
{
    /** @var list<array{to: string, body: string, template: string|null}> */
    public array $sent = [];

    private int $sequence = 0;

    public function sendMessage(string $to, string $body, ?string $templateName = null): string
    {
        $this->sequence++;
        $this->sent[] = ['to' => $to, 'body' => $body, 'template' => $templateName];

        return 'wamid.TEST'.str_pad((string) $this->sequence, 8, '0', STR_PAD_LEFT);
    }
}
