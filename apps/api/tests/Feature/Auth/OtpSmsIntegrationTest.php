<?php

declare(strict_types=1);

use App\Enums\MessageChannel;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Support\Sms\SmsClient;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    $this->tenant = Tenant::factory()->domain('acme.test')->create();

    MessageTemplate::query()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'otp',
        'channel' => MessageChannel::Sms->value,
        'category' => 'utility',
        'body' => 'Your code is {{code}}.',
        'active' => true,
    ]);

    // Never let a delivery job reach a real gateway from tests (sync queue).
    $this->whatsapp = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->whatsapp);

    $this->sent = new ArrayObject;
    app()->instance(SmsClient::class, new class($this->sent) implements SmsClient
    {
        public function __construct(private ArrayObject $sent) {}

        public function send(string $to, string $body): string
        {
            $this->sent[] = ['to' => $to, 'body' => $body];

            return 'fake-sms-id';
        }
    });
});

function otpPost(array $data)
{
    return test()
        ->withHeader('Origin', 'http://acme.test')
        ->postJson('http://acme.test/api/v1/auth/otp/request', $data);
}

it('exposes the code for the dev modal when debug is on and no gateway is configured', function () {
    config(['app.debug' => true, 'services.sms.provider' => '', 'services.whatsapp.access_token' => '']);

    $response = otpPost(['identifier' => '+919000000042'])->assertOk();

    expect($response->json('debug_code'))->toMatch('/^\d{6}$/');
});

it('hides the code once an SMS integration is configured', function () {
    config([
        'app.debug' => true,
        'services.sms.provider' => 'msg91',
        'services.sms.msg91.authkey' => 'test-key',
        'services.sms.msg91.flow_id' => 'test-flow',
    ]);

    otpPost(['identifier' => '+919000000042'])
        ->assertOk()
        ->assertJsonMissingPath('debug_code');
});

it('never exposes the code when debug is off', function () {
    config(['app.debug' => false, 'services.sms.provider' => '', 'services.whatsapp.access_token' => '']);

    otpPost(['identifier' => '+919000000042'])
        ->assertOk()
        ->assertJsonMissingPath('debug_code');
});

it('routes phone OTPs over the SMS channel when a gateway is configured', function () {
    config([
        'services.sms.provider' => 'twilio',
        'services.sms.twilio.account_sid' => 'ACtest',
        'services.sms.twilio.auth_token' => 'token',
    ]);

    otpPost(['identifier' => '+919000000042'])->assertOk();

    $message = Message::query()->withoutGlobalScopes()->latest('id')->first();

    expect($message->channel)->toBe(MessageChannel::Sms)
        ->and($message->recipient)->toBe('+919000000042')
        ->and(count($this->sent))->toBe(1)
        ->and($this->sent[0]['body'])->toContain('Your code is');
});

it('falls back to WhatsApp for phone OTPs when no SMS gateway is configured', function () {
    config(['services.sms.provider' => '', 'services.whatsapp.access_token' => '']);

    MessageTemplate::query()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'otp',
        'channel' => MessageChannel::WhatsApp->value,
        'category' => 'utility',
        'body' => 'Your code is {{code}}.',
        'active' => true,
    ]);

    otpPost(['identifier' => '+919000000042'])->assertOk();

    $message = Message::query()->withoutGlobalScopes()->latest('id')->first();

    expect($message->channel)->toBe(MessageChannel::WhatsApp)
        ->and(count($this->sent))->toBe(0)
        ->and($this->whatsapp->sent)->toHaveCount(1);
});

it('does not use another tenants OTP template (cross-tenant denial)', function () {
    config([
        'services.sms.provider' => 'twilio',
        'services.sms.twilio.account_sid' => 'ACtest',
        'services.sms.twilio.auth_token' => 'token',
    ]);

    // Tenant B has no 'otp' template of its own — only tenant A's exists.
    Tenant::factory()->domain('other.test')->create();

    test()
        ->withHeader('Origin', 'http://other.test')
        ->postJson('http://other.test/api/v1/auth/otp/request', ['identifier' => '+919000000099'])
        ->assertOk();

    expect(Message::query()->withoutGlobalScopes()->where('recipient', '+919000000099')->exists())->toBeFalse()
        ->and(count($this->sent))->toBe(0);
});
