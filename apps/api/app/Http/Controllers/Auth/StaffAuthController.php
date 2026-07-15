<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteStaffTwoFactor;
use App\Actions\Auth\StaffLogin;
use App\Http\Controllers\Auth\Concerns\LogsInUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Requests\Auth\StaffTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Staff sign-in: email + password, then an emailed OTP second factor when 2FA
 * is enabled. Tenant resolved from the request host.
 */
final class StaffAuthController extends Controller
{
    use LogsInUsers;

    public function login(StaffLoginRequest $request, StaffLogin $staffLogin): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $result = $staffLogin->handle(
            $tenant,
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if ($result['status'] === '2fa_required') {
            return response()->json(['status' => '2fa_required']);
        }

        $this->startSession($request, $result['user']);

        return response()->json([
            'status' => 'authenticated',
            'user' => new UserResource($result['user']->load('roles')),
        ]);
    }

    public function verify(StaffTwoFactorRequest $request, CompleteStaffTwoFactor $complete): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $user = $complete->handle(
            $tenant,
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

        $this->startSession($request, $user);

        return response()->json([
            'status' => 'authenticated',
            'user' => new UserResource($user->load('roles')),
        ]);
    }
}
