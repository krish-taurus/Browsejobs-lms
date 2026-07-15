<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Second factor of staff sign-in: verifies the emailed OTP challenge and
 * returns the authenticated staff user.
 */
final readonly class CompleteStaffTwoFactor
{
    public function __construct(private VerifyOtp $verifyOtp) {}

    public function handle(Tenant $tenant, string $email, string $code): User
    {
        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $this->verifyOtp->handle($tenant, $email, OtpPurpose::StaffTwoFactor, $code);

        return $user;
    }
}
