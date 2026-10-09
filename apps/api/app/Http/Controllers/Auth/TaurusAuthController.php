<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Taurus\ClaimWorkspaceInvite;
use App\Actions\Taurus\TaurusLogin;
use App\Http\Controllers\Auth\Concerns\LogsInUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taurus\ClaimInviteRequest;
use App\Http\Requests\Taurus\TaurusLoginRequest;
use App\Http\Resources\UserResource;
use App\Support\Taurus\WorkspacePresenter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Taurus client sign-in (ADR 0052): claim an invite, or sign in to an
 * existing membership. Tenant resolves from the request host; the session is
 * the same Sanctum SPA session the employer and staff portals use.
 */
final class TaurusAuthController extends Controller
{
    use LogsInUsers;

    public function claim(ClaimInviteRequest $request, ClaimWorkspaceInvite $claim): JsonResponse
    {
        $result = $claim->handle(
            app(TenantContext::class)->get(),
            (string) $request->validated('token'),
            (string) $request->validated('name'),
            (string) $request->validated('password'),
        );

        $this->startSession($request, $result['user']);

        return response()->json([
            'status' => 'authenticated',
            'user' => new UserResource($result['user']->load('roles')),
            'workspace' => WorkspacePresenter::forMember($result['workspace'], $result['member']->role->value),
        ]);
    }

    public function login(TaurusLoginRequest $request, TaurusLogin $login): JsonResponse
    {
        $user = $login->handle(
            app(TenantContext::class)->get(),
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        $this->startSession($request, $user);

        return response()->json([
            'status' => 'authenticated',
            'user' => new UserResource($user->load('roles')),
        ]);
    }
}
