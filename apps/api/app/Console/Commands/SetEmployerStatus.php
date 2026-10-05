<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Suspend an employer, or let them back in.
 *
 * Mirrors what the admin panel's status endpoint does, so the CRM can reach it
 * across the bridge without a second set of rules. Only active and suspended
 * are settable: `pending` is what a workspace *is* until its owner claims the
 * invite, not a state anybody chooses.
 *
 * Suspending cuts a paying customer's whole team out of the portal, so it is
 * audited the same way the admin panel audits it.
 */
final class SetEmployerStatus extends Command
{
    protected $signature = 'employer:set-status
        {workspace : Workspace id, or its exact name}
        {status : active or suspended}
        {--tenant= : Tenant id, when the install has more than one}';

    protected $description = 'Suspend an employer workspace, or restore it.';

    public function handle(AuditLogger $audit): int
    {
        $status = mb_strtolower(trim((string) $this->argument('status')));

        $allowed = [EmployerWorkspace::STATUS_ACTIVE, EmployerWorkspace::STATUS_SUSPENDED];

        if (! in_array($status, $allowed, true)) {
            $this->error('Status must be one of: '.implode(', ', $allowed).'.');

            return self::FAILURE;
        }

        $tenant = $this->option('tenant') !== null
            ? Tenant::query()->find((int) $this->option('tenant'))
            : Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($status, $audit): int {
            $key = trim((string) $this->argument('workspace'));

            $workspace = ctype_digit($key)
                ? EmployerWorkspace::query()->find((int) $key)
                : EmployerWorkspace::query()->where('name', $key)->first();

            if ($workspace === null) {
                $this->error("No employer workspace matching [{$key}].");

                return self::FAILURE;
            }

            $before = (string) $workspace->status;

            if ($before === $status) {
                $this->info("{$workspace->name} is already {$status}.");

                return self::SUCCESS;
            }

            $workspace->forceFill(['status' => $status])->save();

            $actor = User::query()->withoutGlobalScopes()
                ->whereIn('user_type', ['admin', 'staff'])
                ->orderBy('id')
                ->first();

            $audit->log('employer.access_changed', $workspace, [
                'from' => $before,
                'to' => $status,
                'via' => 'crm',
            ], $actor);

            $this->info($status === EmployerWorkspace::STATUS_SUSPENDED
                ? "{$workspace->name} is suspended. Their team can no longer sign in."
                : "{$workspace->name} is active again.");

            return self::SUCCESS;
        });
    }
}
