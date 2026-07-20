<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Team\CreateStaffUser;
use App\Actions\Team\SetStaffActive;
use App\Actions\Team\UpdateStaffUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreStaffRequest;
use App\Http\Requests\Team\UpdateStaffRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Team management (institute admin): list, create and manage staff accounts —
 * trainers, mentors, counselors and the like — and assign their roles. Behind
 * `can:manage-users`; every staff row is tenant-scoped by the global scope.
 */
final class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $staff = User::query()
            ->where('user_type', 'staff')
            ->with('roles')
            ->orderBy('name')
            ->get();

        $assignable = Role::query()
            ->whereIn('slug', ['admin', 'trainer', 'mentor', 'counselor', 'placement-officer', 'support-agent'])
            ->orderBy('id')
            ->get(['slug', 'name']);

        return response()->json([
            'data' => UserResource::collection($staff),
            'roles' => $assignable,
        ]);
    }

    public function store(StoreStaffRequest $request, CreateStaffUser $create): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        /** @var array{name: string, email: string, phone?: string|null, password: string, roles: list<string>} $data */
        $data = $request->validated();
        $user = $create->handle($tenant, $data);

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function update(UpdateStaffRequest $request, User $user, UpdateStaffUser $update): JsonResponse
    {
        abort_unless($user->user_type === 'staff', 404);

        /** @var array{name?: string, phone?: string|null, password?: string|null, roles?: list<string>} $data */
        $data = $request->validated();
        $user = $update->handle($user, $data);

        return response()->json(['data' => new UserResource($user)]);
    }

    public function setActive(Request $request, User $user, SetStaffActive $setActive): JsonResponse
    {
        abort_unless($user->user_type === 'staff', 404);
        abort_if($user->getKey() === $request->user()?->getKey(), 422, 'You cannot deactivate your own account.');

        $validated = $request->validate(['active' => ['required', 'boolean']]);
        $user = $setActive->handle($user, (bool) $validated['active']);

        return response()->json(['data' => new UserResource($user)]);
    }
}
