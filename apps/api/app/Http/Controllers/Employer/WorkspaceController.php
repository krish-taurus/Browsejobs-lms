<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\RegisterEmployerWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\StoreWorkspaceRequest;
use App\Http\Requests\Employers\UpdateWorkspaceRequest;
use App\Http\Resources\EmployerWorkspaceResource;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request): JsonResponse
    {
        $workspaces = EmployerWorkspace::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return EmployerWorkspaceResource::collection($workspaces)->response();
    }

    public function store(StoreWorkspaceRequest $request, RegisterEmployerWorkspace $register): JsonResponse
    {
        $workspace = $register->handle($request->user(), $request->validated());

        return (new EmployerWorkspaceResource($workspace))->response()->setStatusCode(201);
    }

    public function show(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        return (new EmployerWorkspaceResource($workspace->loadCount('members')))->response();
    }

    public function update(UpdateWorkspaceRequest $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->ownerOrFail($workspace, $request->user());

        $workspace->update($request->validated());

        return (new EmployerWorkspaceResource($workspace->fresh()->loadCount('members')))->response();
    }
}
