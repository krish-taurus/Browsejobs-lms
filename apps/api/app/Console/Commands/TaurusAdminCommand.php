<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Taurus\CannotSuspendHq;
use App\Actions\Taurus\CreateClientWorkspace;
use App\Actions\Taurus\InviteToWorkspace;
use App\Actions\Taurus\UpdateWorkspace;
use App\Enums\TaurusWorkspaceRole;
use App\Http\Middleware\EnsureTaurusOwner;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceInvite;
use App\Models\TaurusWorkspaceMember;
use App\Models\User;
use App\Support\Taurus\WorkspacePresenter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The Taurus owner console for the CRM (ADR 0052), which runs LMS commands
 * instead of calling the API. Same actions and rules as the owner's admin
 * API (WorkspaceAdminController): the --actor must be a super-admin on the
 * Taurus owner list, and everything happens inside that person's tenant.
 *
 *   taurus:admin list   --actor=you@x
 *   taurus:admin create --actor=you@x --name="Acme" --email=owner@acme.com [--owner-name="Asha"]
 *   taurus:admin invite --actor=you@x --workspace=3 --email=a@acme.com --role=admin [--name=…]
 *   taurus:admin status --actor=you@x --workspace=3 --status=suspended|active
 *
 * Prints one JSON line: {"ok":true,...} or {"ok":false,"error":"…"}. A refused
 * request (wrong actor, bad input) still exits 0 — the JSON carries the
 * answer, so the CRM shows the reason instead of a failed process.
 */
final class TaurusAdminCommand extends Command
{
    protected $signature = 'taurus:admin
        {action : list | create | invite | status}
        {--actor= : Email of the Taurus owner acting}
        {--name= : Workspace name (create) or invitee name (invite)}
        {--email= : Owner email (create) or invitee email (invite)}
        {--owner-name= : Owner name (create)}
        {--workspace= : Workspace id (invite, status)}
        {--role=owner : owner | admin | viewer (invite)}
        {--status= : active | suspended (status)}';

    protected $description = 'Create Taurus client workspaces, invite people, suspend or reactivate (for the CRM).';

    public function handle(CreateClientWorkspace $create, InviteToWorkspace $invite, UpdateWorkspace $update): int
    {
        $actor = $this->actor();
        if ($actor === null) {
            return $this->refuse('Only the Taurus owner (a super-admin on the owner list) can manage Taurus workspaces.');
        }

        try {
            return app(TenantContext::class)->run($actor->tenant, fn (): int => match ((string) $this->argument('action')) {
                'list' => $this->listAll(),
                'create' => $this->create($create, $actor),
                'invite' => $this->invite($invite, $actor),
                'status' => $this->status($update, $actor),
                default => $this->refuse('Unknown action. Use list, create, invite or status.'),
            });
        } catch (CannotSuspendHq $e) {
            return $this->refuse($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->refuse('Could not do that: '.$e->getMessage());
        }
    }

    private function listAll(): int
    {
        $workspaces = TaurusWorkspace::query()
            ->withCount(['members', 'agents'])
            ->withMax('agents', 'last_seen_at')
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get();

        $members = TaurusWorkspaceMember::query()
            ->whereIn('taurus_workspace_id', $workspaces->pluck('id'))
            ->with('user:id,name,email')
            ->get()
            ->groupBy('taurus_workspace_id');
        $invites = TaurusWorkspaceInvite::query()
            ->whereIn('taurus_workspace_id', $workspaces->pluck('id'))
            ->latest('id')
            ->get()
            ->groupBy('taurus_workspace_id');

        return $this->ok(['workspaces' => $workspaces->map(fn (TaurusWorkspace $ws): array => [
            ...WorkspacePresenter::forAdmin($ws),
            'members' => ($members[$ws->id] ?? collect())->map(fn ($m): array => [
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role instanceof TaurusWorkspaceRole ? $m->role->value : (string) $m->role,
            ])->values()->all(),
            'invites' => ($invites[$ws->id] ?? collect())->map(fn (TaurusWorkspaceInvite $i): array => [
                'email' => $i->email,
                'name' => $i->name,
                'role' => $i->role instanceof TaurusWorkspaceRole ? $i->role->value : (string) $i->role,
                'expires_at' => $i->expires_at?->toIso8601String(),
                'accepted_at' => $i->accepted_at?->toIso8601String(),
            ])->values()->all(),
        ])->values()->all()]);
    }

    private function create(CreateClientWorkspace $create, User $actor): int
    {
        $data = $this->validated([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            'owner_name' => ['nullable', 'string', 'max:120'],
        ], ['name' => $this->option('name'), 'email' => $this->option('email'), 'owner_name' => $this->option('owner-name')]);
        if (is_string($data)) {
            return $this->refuse($data);
        }

        $result = $create->handle($data['name'], $data['email'], $data['owner_name'] ?? null, $actor);

        return $this->ok(['workspace' => WorkspacePresenter::forAdmin($result['workspace']->loadCount(['members', 'agents'])), 'invite_url' => $result['invite_url']]);
    }

    private function invite(InviteToWorkspace $invite, User $actor): int
    {
        $data = $this->validated([
            'workspace' => ['required', 'integer'],
            'email' => ['required', 'email', 'max:191'],
            'role' => ['required', Rule::in(TaurusWorkspaceRole::values())],
            'name' => ['nullable', 'string', 'max:120'],
        ], ['workspace' => $this->option('workspace'), 'email' => $this->option('email'), 'role' => $this->option('role'), 'name' => $this->option('name')]);
        if (is_string($data)) {
            return $this->refuse($data);
        }

        $workspace = TaurusWorkspace::query()->find((int) $data['workspace']);
        if ($workspace === null) {
            return $this->refuse('That workspace does not exist.');
        }

        $result = $invite->handle($workspace, $data['email'], TaurusWorkspaceRole::from($data['role']), $data['name'] ?? null, $actor);

        return $this->ok(['invite_url' => $result['url'], 'expires_at' => $result['invite']->expires_at->toIso8601String()]);
    }

    private function status(UpdateWorkspace $update, User $actor): int
    {
        $data = $this->validated([
            'workspace' => ['required', 'integer'],
            'status' => ['required', Rule::in([TaurusWorkspace::STATUS_ACTIVE, TaurusWorkspace::STATUS_SUSPENDED])],
        ], ['workspace' => $this->option('workspace'), 'status' => $this->option('status')]);
        if (is_string($data)) {
            return $this->refuse($data);
        }

        $workspace = TaurusWorkspace::query()->find((int) $data['workspace']);
        if ($workspace === null) {
            return $this->refuse('That workspace does not exist.');
        }

        $update->handle($workspace, $data['status'], null, $actor);

        return $this->ok(['workspace' => WorkspacePresenter::forAdmin($workspace->loadCount(['members', 'agents']))]);
    }

    /** The acting owner: a super-admin, on the owner list when one is set. */
    private function actor(): ?User
    {
        $email = mb_strtolower(trim((string) $this->option('actor')));
        if ($email === '') {
            return null;
        }

        $user = User::query()->withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->get()
            ->first(fn (User $u): bool => $u->hasRole('super-admin'));
        $owners = EnsureTaurusOwner::ownerEmails();

        return $user !== null && ($owners === [] || in_array($email, $owners, true)) ? $user : null;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|string the clean input, or the first error
     */
    private function validated(array $rules, array $input): array|string
    {
        $validator = Validator::make($input, $rules);

        return $validator->fails() ? (string) $validator->errors()->first() : $validator->validated();
    }

    /** @param  array<string, mixed>  $data */
    private function ok(array $data): int
    {
        $this->line((string) json_encode(['ok' => true, ...$data]));

        return self::SUCCESS;
    }

    private function refuse(string $message): int
    {
        $this->line((string) json_encode(['ok' => false, 'error' => $message]));

        return self::SUCCESS;
    }
}
