<?php

declare(strict_types=1);

namespace App\Support\Sms;

use App\Support\Crm\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MSG91 Flow API client (Indian DLT-compliant SMS). DLT rules require every
 * message to match a registered template, so the rendered body is passed as
 * the single `body` variable of the configured flow — the flow template must
 * be `##body##` (or map the variable it needs).
 */
final class Msg91SmsClient implements SmsClient
{
    /**
     * @param  array{authkey: string, flow_id: string, sender: string, base_url: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $body): string
    {
        $response = Http::withHeaders(['authkey' => $this->config['authkey']])
            ->acceptJson()
            ->post("{$this->config['base_url']}/api/v5/flow/", [
                'flow_id' => $this->config['flow_id'],
                'sender' => $this->config['sender'],
                'recipients' => [[
                    'mobiles' => ltrim(PhoneNormalizer::normalize($to), '+'),
                    'body' => $body,
                ]],
            ])
            ->throw()
            ->json();

        $id = $response['data'] ?? $response['request_id'] ?? null;
        if (! is_string($id)) {
            throw new RuntimeException('MSG91 did not return a request id.');
        }

        return $id;
    }
}
