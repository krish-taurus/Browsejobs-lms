<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * First factor of staff sign-in: verifies email + password within the tenant.
 * When the account has 2FA enabled, issues an email OTP challenge and reports
 * `2fa_required`; the caller must then complete {@see CompleteStaffTwoFactor}.
 */
final readonly class StaffLogin
{
    public function __construct(private RequestOtp $requestOtp) {}

    /**
     * @return array{status: 'authenticated'|'2fa_required', user: User}
     */
    public function handle(Tenant $tenant, string $email, string $password): array
    {
        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->isStaff()) {
            throw ValidationException::withMessages([
                'email' => 'This account is not permitted to sign in here.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact your administrator.',
            ]);
        }

        if ($user->two_factor_enabled) {
            $this->requestOtp->handle($tenant, $user->email, OtpChannel::Email, OtpPurpose::StaffTwoFactor);

            return ['status' => '2fa_required', 'user' => $user];
        }

        return ['status' => 'authenticated', 'user' => $user];
    }
}
