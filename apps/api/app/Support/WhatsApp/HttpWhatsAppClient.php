<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use App\Support\Crm\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real WhatsApp Cloud API client. Only invoked from queued jobs; unit tests use
 * {@see FakeWhatsAppClient}.
 */
final class HttpWhatsAppClient implements WhatsAppClient
{
    /**
     * @param  array{phone_number_id: string, access_token: string, base_url: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function sendMessage(string $to, string $body, ?string $templateName = null): string
    {
        $payload = $templateName !== null
            ? [
                'messaging_product' => 'whatsapp',
                'to' => PhoneNormalizer::normalize($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [['type' => 'text', 'text' => $body]],
                    ]],
                ],
            ]
            : [
                'messaging_product' => 'whatsapp',
                'to' => PhoneNormalizer::normalize($to),
                'type' => 'text',
                'text' => ['body' => $body],
            ];

        $response = Http::withToken($this->config['access_token'])
            ->acceptJson()
            ->post("{$this->config['base_url']}/{$this->config['phone_number_id']}/messages", $payload)
            ->throw()
            ->json();

        $id = $response['messages'][0]['id'] ?? null;
        if (! is_string($id)) {
            throw new RuntimeException('WhatsApp API did not return a message id.');
        }

        return $id;
    }
}
