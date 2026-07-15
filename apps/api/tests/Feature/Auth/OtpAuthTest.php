<?php

declare(strict_types=1);

use App\Actions\Auth\RequestOtp;
use App\Actions\Auth\VerifyOtp;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\Tenant;
use App\Support\Otp\OtpNotifier;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->codes = new ArrayObject;

    app()->instance(OtpNotifier::class, new class($this->codes) implements OtpNotifier
    {
        public function __construct(private ArrayObject $codes) {}

        public function send(string $identifier, OtpChannel $channel, string $code, OtpPurpose $purpose): void
        {
            $this->codes[$identifier] = $code;
        }
    });

    $this->tenant = Tenant::factory()->create();
});

it('issues a hashed six-digit code and delivers the plaintext over the channel', function () {
    app(RequestOtp::class)->handle($this->tenant, '+919000000001', OtpChannel::Sms, OtpPurpose::Login);

    $otp = OtpCode::query()->first();

    expect($this->codes['+919000000001'])->toMatch('/^\d{6}$/')
        ->and($otp->code_hash)->not->toBe($this->codes['+919000000001'])
        ->and($otp->consumed_at)->toBeNull()
        ->and($otp->expires_at->isFuture())->toBeTrue();
});

it('verifies a correct code once and rejects reuse (single-use)', function () {
    app(RequestOtp::class)->handle($this->tenant, 'stu@acme.test', OtpChannel::Email, OtpPurpose::Login);
    $code = $this->codes['stu@acme.test'];

    $verify = app(VerifyOtp::class);
    $consumed = $verify->handle($this->tenant, 'stu@acme.test', OtpPurpose::Login, $code);

    expect($consumed->consumed_at)->not->toBeNull();

    expect(fn () => $verify->handle($this->tenant, 'stu@acme.test', OtpPurpose::Login, $code))
        ->toThrow(ValidationException::class);
});

it('rejects a wrong code and burns an attempt', function () {
    app(RequestOtp::class)->handle($this->tenant, 'stu@acme.test', OtpChannel::Email, OtpPurpose::Login);

    expect(fn () => app(VerifyOtp::class)->handle($this->tenant, 'stu@acme.test', OtpPurpose::Login, '000000'))
        ->toThrow(ValidationException::class);

    expect(OtpCode::query()->first()->attempts)->toBe(1);
});

it('rejects an expired code', function () {
    app(RequestOtp::class)->handle($this->tenant, 'stu@acme.test', OtpChannel::Email, OtpPurpose::Login);
    $code = $this->codes['stu@acme.test'];

    OtpCode::query()->first()->update(['expires_at' => now()->subMinute()]);

    expect(fn () => app(VerifyOtp::class)->handle($this->tenant, 'stu@acme.test', OtpPurpose::Login, $code))
        ->toThrow(ValidationException::class);
});

it('rate limits repeated code requests for the same identifier', function () {
    $request = app(RequestOtp::class);

    for ($i = 0; $i < 5; $i++) {
        $request->handle($this->tenant, '+919000000002', OtpChannel::Sms, OtpPurpose::Login);
    }

    expect(fn () => $request->handle($this->tenant, '+919000000002', OtpChannel::Sms, OtpPurpose::Login))
        ->toThrow(ValidationException::class);
});

it('does not let one tenant verify another tenants code', function () {
    $other = Tenant::factory()->create();

    app(RequestOtp::class)->handle($this->tenant, 'stu@acme.test', OtpChannel::Email, OtpPurpose::Login);
    $code = $this->codes['stu@acme.test'];

    expect(fn () => app(VerifyOtp::class)->handle($other, 'stu@acme.test', OtpPurpose::Login, $code))
        ->toThrow(ValidationException::class);
});
