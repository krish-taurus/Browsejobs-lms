<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Employers\OnboardEmployer;
use App\Http\Controllers\Controller;
use App\Models\EmployerInvite;
use App\Models\EmployerWorkspace;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-side employer management (PRD-E F1): onboard a new employer, see who
 * is on the platform, and suspend or restore portal access.
 *
 * This is the sales-led path. Until now the only ways in were an employer
 * self-registering or someone running a console command on the server, which
 * meant ops could not onboard a signed customer without an engineer.
 */
final class EmployerAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = EmployerWorkspace::query()
            ->withCount(['members', 'jobs'])
            ->with(['members' => fn ($q) => $q->where('role', 'owner')->with('user:id,name,email')])
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => $workspaces->getCollection()->map(function (EmployerWorkspace $w): array {
                $owner = $w->members->first()?->user;

                return [
                    'id' => $w->id,
                    'name' => $w->name,
                    'slug' => $w->slug,
                    'industry' => $w->industry,
                    'company_size' => $w->company_size,
                    'status' => $w->status,
                    'members_count' => $w->members_count,
                    'jobs_count' => $w->jobs_count,
                    'owner' => $owner === null ? null : [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                    ],
                    // Surfaced so ops can tell "invited but never signed in"
                    // from "active", which is the difference between chasing
                    // a customer and leaving them alone.
                    'pending_invite' => $this->pendingInvite($w),
                    'created_at' => $w->created_at?->toIso8601String(),
                ];
            })->all(),
            'meta' => [
                'total' => $workspaces->total(),
                'current_page' => $workspaces->currentPage(),
                'last_page' => $workspaces->lastPage(),
            ],
        ]);
    }

    /**
     * Create the workspace, the owner's account and a single-use invite.
     *
     * The response carries the claim URL exactly once. It is not stored in
     * the audit log and cannot be read back later — if it is lost, ops
     * re-invites rather than retrieving a live credential-setting link from
     * a screen more people can see than should be able to use it.
     */
    public function store(Request $request, OnboardEmployer $action): JsonResponse
    {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:160'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'company_size' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $tenant = app(TenantContext::class)->get();
        $result = $action->handle($tenant, $validated, $request->user());

        return response()->json(['data' => [
            'workspace' => [
                'id' => $result['workspace']->id,
                'name' => $result['workspace']->name,
                'slug' => $result['workspace']->slug,
            ],
            'owner' => [
                'id' => $result['owner']->id,
                'name' => $result['owner']->name,
                'email' => $result['owner']->email,
            ],
            'invite' => [
                'claim_url' => rtrim((string) config('app.frontend_url'), '/')
                    .'/employer/claim/'.$result['invite']->token,
                'expires_at' => $result['invite']->expires_at?->toIso8601String(),
                'shown_once' => true,
            ],
        ]], 201);
    }

    /** Suspend or restore an employer's access to the portal. */
    public function status(
        Request $request,
        EmployerWorkspace $workspace,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in([EmployerWorkspace::STATUS_ACTIVE, EmployerWorkspace::STATUS_SUSPENDED])],
        ]);

        $before = $workspace->status;
        $workspace->forceFill(['status' => $validated['status']])->save();

        // Access changes are audited (CLAUDE.md): cutting a paying customer's
        // team out of the portal is not something that should be untraceable.
        $audit->log('employer.access_changed', $workspace, [
            'from' => $before,
            'to' => $validated['status'],
        ], $request->user());

        return response()->json(['data' => ['id' => $workspace->id, 'status' => $workspace->status]]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingInvite(EmployerWorkspace $workspace): ?array
    {
        $invite = EmployerInvite::query()
            ->where('employer_workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return $invite === null ? null : [
            'email' => $invite->email,
            'expires_at' => $invite->expires_at?->toIso8601String(),
            // The token is never returned here. It is shown once, at creation.
        ];
    }
}
