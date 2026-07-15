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

        $requestOtp->handle($tenant, $request->string('identifier')->toString(), $request->channel(), OtpPurpose::Login);

        return response()->json(['status' => 'otp_sent']);
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyOtp $verifyOtp): JsonResource
    {
        $tenant = app(TenantContext::class)->get();
        $identifier = $request->string('identifier')->toString();

        $verifyOtp->handle($tenant, $identifier, OtpPurpose::Login, $request->string('code')->toString());

        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->where('email', $identifier)->orWhere('phone', $identifier))
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'identifier' => 'No account is registered with these details.',
            ]);
        }

        $this->startSession($request, $user);

        return new UserResource($user->load('roles'));
    }
}
