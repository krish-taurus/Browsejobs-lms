<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EmployerLogin;
use App\Http\Controllers\Auth\Concerns\LogsInUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Resources\UserResource;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Employer sign-in (PRD-E F1). Reuses the staff login request shape
 * (email + password); tenant resolves from the request host.
 */
final class EmployerAuthController extends Controller
{
    use LogsInUsers;

    public function login(StaffLoginRequest $request, EmployerLogin $employerLogin): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $result = $employerLogin->handle(
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
}
