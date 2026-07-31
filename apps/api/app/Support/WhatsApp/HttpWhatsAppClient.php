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
     * @param  array{phone_number_id: string, access_token: string, base_url: string, auth_templates?: list<string>}  $config
     */
    public function __construct(private readonly array $config) {}

    public function sendMessage(string $to, string $body, ?string $templateName = null, array $parameters = []): string
    {
        $bodyParams = $parameters !== [] ? $parameters : [$body];

        $components = [[
            'type' => 'body',
            'parameters' => array_map(
                fn (string $value) => ['type' => 'text', 'text' => $value],
                $bodyParams,
            ),
        ]];

        // AUTHENTICATION templates (OTP) carry a copy-code button whose
        // parameter is the code itself — Meta rejects the send without it.
        if ($templateName !== null && in_array($templateName, $this->config['auth_templates'] ?? [], true)) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $bodyParams[0]]],
            ];
        }

        $payload = $templateName !== null
            ? [
                'messaging_product' => 'whatsapp',
                'to' => $this->toE164($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en'],
                    'components' => $components,
                ],
            ]
            : [
                'messaging_product' => 'whatsapp',
                'to' => $this->toE164($to),
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

    public function sendImage(string $to, string $imageIdOrUrl, string $caption): string
    {
        $image = str_starts_with($imageIdOrUrl, 'http')
            ? ['link' => $imageIdOrUrl]
            : ['id' => $imageIdOrUrl];

        $response = Http::withToken($this->config['access_token'])
            ->acceptJson()
            ->post("{$this->config['base_url']}/{$this->config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->toE164($to),
                'type' => 'image',
                'image' => $image + ['caption' => $caption],
            ])
            ->throw()
            ->json();

        $id = $response['messages'][0]['id'] ?? null;
        if (! is_string($id)) {
            throw new RuntimeException('WhatsApp API did not return a message id.');
        }

        return $id;
    }

    /**
     * Meta's Cloud API needs E.164 without the plus. Bare 10-digit local
     * numbers (how leads usually type them) get the default country code.
     */
    private function toE164(string $phone): string
    {
        $digits = PhoneNormalizer::normalize($phone);

        if (strlen($digits) === 10) {
            return ($this->config['default_country_code'] ?? '91').$digits;
        }

        return $digits;
    }
}
