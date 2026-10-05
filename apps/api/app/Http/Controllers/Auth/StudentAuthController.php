<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RequestOtp;
use App\Actions\Auth\VerifyOtp;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Auth\Concerns\LogsInUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

/**
 * Student sign-in by phone/email OTP. Tenant is resolved from the request host
 * (tenant.domain middleware). On success a Sanctum SPA session is established.
 */
final class StudentAuthController extends Controller
{
    use LogsInUsers;

    public function requestOtp(RequestOtpRequest $request, RequestOtp $requestOtp): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $identifier = $request->string('identifier')->toString();

        // Sign-in and sign-up are two different pages here — "Create an
        // account" is one tap away — so there's nothing to protect by
        // staying silent about whether a number is registered, and staying
        // silent was costing an OTP send (SMS/WhatsApp) on every attempt
        // that could only ever fail at verify anyway. Told upfront instead
        // (Sept 2026 fix): no code sent, no wasted round trip through "enter
        // your code" for an account that was never going to exist.
        $this->assertRegistered($tenant->id, $identifier);

        $requestOtp->handle($tenant, $identifier, $request->channel(), OtpPurpose::Login);

        return response()->json(['status' => 'otp_sent']);
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyOtp $verifyOtp): JsonResource
    {
        $tenant = app(TenantContext::class)->get();
        $identifier = $request->string('identifier')->toString();

        $verifyOtp->handle($tenant, $identifier, OtpPurpose::Login, $request->string('code')->toString());

        $user = $this->findByIdentifier($tenant->id, $identifier);

        if ($user === null) {
            throw ValidationException::withMessages([
                'identifier' => 'No account is registered with these details.',
            ]);
        }

        $this->startSession($request, $user);

        return new UserResource($user->load('roles'));
    }

    private function assertRegistered(int $tenantId, string $identifier): void
    {
        if ($this->findByIdentifier($tenantId, $identifier) === null) {
            throw ValidationException::withMessages([
                'identifier' => "We don't have an account with these details yet — create an account first.",
            ]);
        }
    }

    // Match the phone on its last 10 digits: the same person is stored as
    // "8114637479" when they self-register and "+918114637479" when the
    // funnel creates their account, and an exact match made those two
    // different logins. Oldest account wins so the result is stable.
    private function findByIdentifier(int $tenantId, string $identifier): ?User
    {
        $last10 = substr(PhoneNormalizer::normalize($identifier), -10);

        return User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($identifier, $last10) {
                $q->where('email', $identifier);

                if (strlen($last10) === 10) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ['%'.$last10]);
                } else {
                    $q->orWhere('phone', $identifier);
                }
            })
            ->orderBy('id')
            ->first();
    }
}
