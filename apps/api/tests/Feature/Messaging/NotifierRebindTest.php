<?php

declare(strict_types=1);

use App\Actions\Auth\RequestOtp;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\Instalment;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Notifications\FeeNotifier;
use App\Support\Notifications\MessengerFeeNotifier;
use App\Support\Notifications\MessengerSessionNotifier;
use App\Support\Notifications\SessionNotifier;
use App\Support\Otp\MessengerOtpNotifier;
use App\Support\Otp\OtpNotifier;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();
});

it('binds the messenger implementations for every notifier', function () {
    expect(app(OtpNotifier::class))->toBeInstanceOf(MessengerOtpNotifier::class)
        ->and(app(FeeNotifier::class))->toBeInstanceOf(MessengerFeeNotifier::class)
        ->and(app(SessionNotifier::class))->toBeInstanceOf(MessengerSessionNotifier::class);
});

it('sends an OTP through the messenger', function () {
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'otp', 'channel' => 'whatsapp', 'body' => 'Your code is {{code}}.',
    ]);

    withinTenant($this->tenant, fn () => app(RequestOtp::class)->handle(
        $this->tenant, '+91 90000 55555', OtpChannel::Sms, OtpPurpose::Login,
    ));

    $message = Message::withoutGlobalScopes()->where('template_key', 'otp')->first();
    expect($message)->not->toBeNull()
        ->and($message->recipient)->toBe('+91 90000 55555')
        ->and($this->wa->sent)->toHaveCount(1)
        ->and($this->wa->sent[0]['body'])->toContain('Your code is');
});

it('sends a dunning reminder through the messenger with the pay link', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 66666']);
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'fee_reminder', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}, pay {{amount}}: {{link}}',
    ]);
    $instalment = Instalment::factory()->for($this->tenant)->create(['amount_paise' => 1_000_000]);

    withinTenant($this->tenant, fn () => app(FeeNotifier::class)->reminder($student, $instalment, 't-1', 'https://rzp.test/l/x'));

    $message = Message::withoutGlobalScopes()->where('template_key', 'fee_reminder')->first();
    expect($message)->not->toBeNull()
        ->and($message->body)->toContain('https://rzp.test/l/x')
        ->and($message->body)->toContain('10,000.00');
});
