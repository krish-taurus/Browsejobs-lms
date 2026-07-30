<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\ChangeEmployerMemberRole;
use App\Actions\Employers\InviteEmployerMember;
use App\Actions\Employers\RemoveEmployerMember;
use App\Enums\EmployerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\InviteMemberRequest;
use App\Http\Requests\Employers\UpdateMemberRoleRequest;
use App\Http\Resources\EmployerInviteResource;
use App\Http\Resources\EmployerMemberResource;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $members = $workspace->members()->with('user')->orderBy('joined_at')->get();

        return EmployerMemberResource::collection($members)->response();
    }

    public function invite(InviteMemberRequest $request, EmployerWorkspace $workspace, InviteEmployerMember $inviteMember): JsonResponse
    {
        $this->ownerOrFail($workspace, $request->user());

        $invite = $inviteMember->handle(
            $workspace,
            $request->user(),
            $request->string('email')->toString(),
            EmployerRole::from($request->string('role')->toString()),
        );

        return (new EmployerInviteResource($invite))->response()->setStatusCode(201);
    }

    public function invites(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->ownerOrFail($workspace, $request->user());

        $invites = $workspace->invites()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return EmployerInviteResource::collection($invites)->response();
    }

    public function updateRole(
        UpdateMemberRoleRequest $request,
        EmployerWorkspace $workspace,
        EmployerMember $member,
        ChangeEmployerMemberRole $changeRole,
    ): JsonResponse {
        $this->ownerOrFail($workspace, $request->user());
        abort_unless($member->employer_workspace_id === $workspace->id, 404);

        $updated = $changeRole->handle(
            $member,
            EmployerRole::from($request->string('role')->toString()),
            $request->user(),
        );

        return (new EmployerMemberResource($updated->load('user')))->response();
    }

    public function destroy(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerMember $member,
        RemoveEmployerMember $removeMember,
    ): JsonResponse {
        $this->ownerOrFail($workspace, $request->user());
        abort_unless($member->employer_workspace_id === $workspace->id, 404);

        $removeMember->handle($member, $request->user());

        return response()->json(['ok' => true]);
    }
}
