<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Taurus;

use App\Actions\Taurus\CannotSuspendHq;
use App\Actions\Taurus\CreateClientWorkspace;
use App\Actions\Taurus\EnsureHqWorkspace;
use App\Actions\Taurus\InviteToWorkspace;
use App\Actions\Taurus\UpdateWorkspace;
use App\Enums\TaurusWorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taurus\CreateWorkspaceRequest;
use App\Http\Requests\Taurus\InviteMemberRequest;
use App\Http\Requests\Taurus\UpdateWorkspaceRequest;
use App\Models\TaurusWorkspace;
use App\Support\Taurus\WorkspacePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The founder's view over every Taurus workspace in the tenant (ADR 0052):
 * create, invite, suspend. Owner-only (`taurus.owner`). It deliberately shows
 * no agent data and no keys — running a client's floor is the client's business.
 */
final class WorkspaceAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = self::withStats(TaurusWorkspace::query())
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get()
            ->map(fn (TaurusWorkspace $ws): array => WorkspacePresenter::forAdmin($ws))
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function store(CreateWorkspaceRequest $request, CreateClientWorkspace $create): JsonResponse
    {
        $result = $create->handle(
            (string) $request->validated('name'),
            (string) $request->validated('owner_email'),
            $request->validated('owner_name'),
            $request->user(),
        );

        return response()->json(['data' => [
            'workspace' => $this->present($result['workspace']),
            'invite_url' => $result['invite_url'],
        ]], 201);
    }

    public function update(UpdateWorkspaceRequest $request, TaurusWorkspace $workspace, UpdateWorkspace $update): JsonResponse
    {
        try {
            $workspace = $update->handle($workspace, $request->validated('status'), $request->validated('name'), $request->user());
        } catch (CannotSuspendHq $e) {
            return response()->json(['error' => ['code' => 'cannot_suspend_hq', 'message' => $e->getMessage()]], 422);
        }

        return response()->json(['data' => $this->present($workspace)]);
    }

    public function invite(InviteMemberRequest $request, TaurusWorkspace $workspace, InviteToWorkspace $invite): JsonResponse
    {
        $result = $invite->handle(
            $workspace,
            (string) $request->validated('email'),
            TaurusWorkspaceRole::from((string) $request->validated('role')),
            $request->validated('name'),
            $request->user(),
        );

        return response()->json(['data' => [
            'invite_url' => $result['url'],
            'expires_at' => $result['invite']->expires_at->toIso8601String(),
        ]], 201);
    }

    /** Ensures HQ exists and the caller is in it; the console calls this on first visit. */
    public function hq(Request $request, EnsureHqWorkspace $ensure): JsonResponse
    {
        $workspace = $ensure->handle($request->user());

        return response()->json(['data' => [
            ...$this->present($workspace),
            'role' => TaurusWorkspaceRole::Owner->value,
        ]]);
    }

    /** @return array<string, mixed> */
    private function present(TaurusWorkspace $workspace): array
    {
        $fresh = self::withStats(TaurusWorkspace::query()->whereKey($workspace->id))->firstOrFail();

        return WorkspacePresenter::forAdmin($fresh);
    }

    /**
     * @param  Builder<TaurusWorkspace>  $query
     * @return Builder<TaurusWorkspace>
     */
    private static function withStats(Builder $query): Builder
    {
        return $query->withCount(['members', 'agents'])->withMax('agents', 'last_seen_at');
    }
}
