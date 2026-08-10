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

    public function sendMessage(
        string $to,
        string $body,
        ?string $templateName = null,
        array $parameters = [],
        bool $authTemplate = false,
    ): string {
        $payload = $templateName !== null
            ? $this->templatePayload($to, $body, $templateName, $parameters, $authTemplate)
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

    /**
     * @param  list<string>  $parameters
     * @return array<string, mixed>
     */
    private function templatePayload(
        string $to,
        string $body,
        string $templateName,
        array $parameters,
        bool $authTemplate,
    ): array {
        $values = $parameters !== [] ? array_values($parameters) : [$body];

        $components = [[
            'type' => 'body',
            'parameters' => array_map(
                static fn (string $value): array => ['type' => 'text', 'text' => $value],
                $values,
            ),
        ]];

        // An AUTHENTICATION template carries the code again in its copy-code
        // button; Meta rejects the send outright if that component is missing.
        if ($authTemplate) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $values[0]]],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => PhoneNormalizer::normalize($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'en'],
                'components' => $components,
            ],
        ];
    }
}
