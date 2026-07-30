<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\EmployerMember;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Employer portal sign-in (PRD-E F1): email + password within the
 * tenant, restricted to accounts that are employer-typed or hold at
 * least one workspace membership. Mirrors StaffLogin, including the
 * emailed-OTP second factor when 2FA is enabled.
 */
final readonly class EmployerLogin
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

        $isEmployer = $user->user_type === 'employer'
            || EmployerMember::withoutGlobalScopes()->where('user_id', $user->id)->exists();

        if (! $isEmployer) {
            throw ValidationException::withMessages([
                'email' => 'This account is not an employer account. Sign in to the portal you registered for.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact support.',
            ]);
        }

        if ($user->two_factor_enabled) {
            $this->requestOtp->handle($tenant, $user->email, OtpChannel::Email, OtpPurpose::StaffTwoFactor);

            return ['status' => '2fa_required', 'user' => $user];
        }

        return ['status' => 'authenticated', 'user' => $user];
    }
}
