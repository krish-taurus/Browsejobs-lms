<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Employers\RegisterEmployerWorkspace;
use App\Enums\EmployerRole;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Provision (or repair) an employer workspace owner from the console.
 *
 * The employer portal has no self-serve signup by design — workspaces are
 * created for a company, and membership is by invite. That leaves no way to
 * bootstrap the very first owner, which is what this exists for.
 *
 * The password is prompted for, never passed as an argument: arguments land
 * in shell history and in process listings. Nothing sensitive is echoed.
 *
 * Idempotent — safe to re-run to reset a password or re-grant ownership.
 */
final class GrantEmployerOwner extends Command
{
    protected $signature = 'employer:grant-owner
        {email : The account to make a workspace owner}
        {--workspace= : Workspace name (defaults to the first, or creates one)}
        {--tenant= : Tenant id, when the install has more than one}
        {--name= : Display name for a newly created account}';

    protected $description = 'Make a user the owner of an employer workspace, creating either if needed.';

    public function handle(AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("[{$email}] is not a valid email address.");

            return self::FAILURE;
        }

        $tenant = $this->option('tenant') !== null
            ? Tenant::query()->find((int) $this->option('tenant'))
            : Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->error('No tenant found. Seed the application first.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($email, $tenant, $audit): int {
            $user = User::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->first();

            $password = $this->secret('Password for the employer portal (input hidden)');
            $confirm = $this->secret('Confirm password');

            if ($password === null || mb_strlen($password) < 12) {
                $this->error('Password must be at least 12 characters.');

                return self::FAILURE;
            }

            if ($password !== $confirm) {
                $this->error('Passwords did not match.');

                return self::FAILURE;
            }

            if ($user === null) {
                $user = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => (string) ($this->option('name') ?? 'Employer admin'),
                    'email' => $email,
                    'password' => Hash::make($password),
                    'user_type' => 'employer',
                    'is_active' => true,
                ]);
                $this->info("Created employer account for {$email}.");
            } else {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'user_type' => 'employer',
                    'is_active' => true,
                ])->save();
                $this->info("Updated the existing account for {$email}.");
            }

            $workspaceName = (string) ($this->option('workspace') ?? '');
            $workspace = $workspaceName !== ''
                ? EmployerWorkspace::query()->where('name', $workspaceName)->first()
                : EmployerWorkspace::query()->orderBy('id')->first();

            if ($workspace === null) {
                // Reuse the registration action so slug generation, the owner
                // membership and the audit entry stay single-sourced.
                $workspace = app(RegisterEmployerWorkspace::class)->handle($user, [
                    'name' => $workspaceName !== '' ? $workspaceName : 'BrowseJobs',
                ]);
                $this->info("Created workspace [{$workspace->name}].");
            }

            EmployerMember::query()->updateOrCreate(
                ['employer_workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['tenant_id' => $tenant->id, 'role' => EmployerRole::Owner->value, 'joined_at' => now()],
            );

            // Granting portal ownership is a privilege change, so it is
            // audited like every other role change.
            $audit->log('employer.owner_granted', $user, [
                'workspace' => $workspace->name,
                'via' => 'console',
            ]);

            $this->newLine();
            $this->info("{$email} is now an Owner of [{$workspace->name}] on tenant [{$tenant->id}].");
            $this->line('Sign in at /employer with that email and the password you just set.');

            return self::SUCCESS;
        });
    }
}
