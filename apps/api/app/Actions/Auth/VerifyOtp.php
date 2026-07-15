<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Verifies an OTP and consumes it atomically (single-use). Wrong codes burn an
 * attempt; the code is locked after too many failures. The consume is a
 * conditional UPDATE so two concurrent verifications can never both succeed.
 */
final readonly class VerifyOtp
{
    private const MAX_ATTEMPTS = 5;

    public function handle(Tenant $tenant, string $identifier, OtpPurpose $purpose, string $code): OtpCode
    {
        $otp = OtpCode::query()
            ->where('tenant_id', $tenant->id)
            ->where('identifier', $identifier)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null || $otp->isExpired()) {
            $this->fail();
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => 'Too many incorrect attempts. Request a new code.',
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $this->fail();
        }

        $consumed = OtpCode::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($otp->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($consumed === 0) {
            throw ValidationException::withMessages([
                'code' => 'This code has already been used.',
            ]);
        }

        return $otp->refresh();
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'code' => 'This code is invalid or has expired.',
        ]);
    }
}
