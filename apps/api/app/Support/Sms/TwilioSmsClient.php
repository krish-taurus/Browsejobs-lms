<?php

declare(strict_types=1);

namespace App\Support\Sms;

use App\Support\Crm\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Twilio Programmable Messaging client. Free-text bodies; `from` is a Twilio
 * number or an alphanumeric sender id where the destination allows it.
 */
final class TwilioSmsClient implements SmsClient
{
    /**
     * @param  array{account_sid: string, auth_token: string, from: string, base_url: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $body): string
    {
        $response = Http::withBasicAuth($this->config['account_sid'], $this->config['auth_token'])
            ->asForm()
            ->acceptJson()
            ->post("{$this->config['base_url']}/2010-04-01/Accounts/{$this->config['account_sid']}/Messages.json", [
                'To' => PhoneNormalizer::normalize($to),
                'From' => $this->config['from'],
                'Body' => $body,
            ])
            ->throw()
            ->json();

        $id = $response['sid'] ?? null;
        if (! is_string($id)) {
            throw new RuntimeException('Twilio did not return a message sid.');
        }

        return $id;
    }
}
