<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AssignRoleToUser;
use App\Actions\Auth\RequestOtp;
use App\Actions\Auth\VerifyOtp;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Auth\Concerns\LogsInUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Student self-registration: details → OTP to phone/email → verify → account
 * created (student role) + session started. Duplicate contacts are rejected
 * up-front so the OTP is never sent for an account that can't be created.
 */
final class RegisterController extends Controller
{
    use LogsInUsers;

    public function request(RegisterRequest $request, RequestOtp $requestOtp): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $this->assertContactAvailable(
            $tenant->id,
            $request->string('phone')->toString(),
            $request->input('email'),
        );

        $requestOtp->handle($tenant, $request->identifier(), $request->channel(), OtpPurpose::Login);

        return response()->json(['status' => 'otp_sent']);
    }

    public function verify(RegisterRequest $request, VerifyOtp $verifyOtp, AssignRoleToUser $assignRole): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $code = (string) $request->input('code', '');

        $this->assertContactAvailable(
            $tenant->id,
            $request->string('phone')->toString(),
            $request->input('email'),
        );

        $verifyOtp->handle($tenant, $request->identifier(), OtpPurpose::Login, $code);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $request->string('name')->toString(),
            'phone' => $request->string('phone')->toString(),
            'email' => $request->input('email') ?: null,
            'user_type' => 'student',
            // DPDP telemetry consent, captured + versioned at signup (PRD §10).
            'telemetry_consent_at' => now(),
            'consent_version' => (string) config('dpdp.consent_version', 'v1'),
        ]);

        $assignRole->handle($user, 'student', actor: $user);

        $this->startSession($request, $user);

        return response()->json(
            ['status' => 'registered', 'user' => new UserResource($user->load('roles'))],
            201,
        );
    }

    private function assertContactAvailable(int $tenantId, string $phone, mixed $email): void
    {
        $exists = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($phone, $email) {
                $query->where('phone', $phone);
                if (is_string($email) && $email !== '') {
                    $query->orWhere('email', $email);
                }
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone' => 'An account already exists with this phone or email — sign in instead.',
            ]);
        }
    }
}
