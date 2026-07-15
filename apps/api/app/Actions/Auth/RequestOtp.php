<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\Tenant;
use App\Support\Otp\OtpNotifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues a one-time passcode for an identifier and delivers it over a channel.
 * Codes expire within the CLAUDE.md auth window (<= 15 min) and requests are
 * rate limited per identifier to blunt enumeration and SMS-pumping abuse.
 */
final readonly class RequestOtp
{
    private const TTL_MINUTES = 10;

    private const MAX_REQUESTS = 5;

    private const WINDOW_SECONDS = 600;

    public function __construct(private OtpNotifier $notifier) {}

    public function handle(Tenant $tenant, string $identifier, OtpChannel $channel, OtpPurpose $purpose): void
    {
        $this->assertNotThrottled($tenant, $identifier);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::query()->create([
            'tenant_id' => $tenant->id,
            'identifier' => $identifier,
            'channel' => $channel->value,
            'purpose' => $purpose->value,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->notifier->send($identifier, $channel, $code, $purpose);
    }

    private function assertNotThrottled(Tenant $tenant, string $identifier): void
    {
        $key = 'otp-request:'.$tenant->id.':'.Str::lower($identifier);

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS)) {
            throw ValidationException::withMessages([
                'identifier' => 'Too many codes requested. Please wait a few minutes and try again.',
            ]);
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);
    }
}
