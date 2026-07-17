<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reports\GenerateCounselorDigest;
use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Counselor daily risk digest (PRD §6.10). Per tenant, delivers a digest to each
 * counselor (staff with `manage-leads`); skips tenants with no counselor.
 */
final class RunCounselorDigests extends Command
{
    protected $signature = 'digest:counselor-daily';

    protected $description = 'Send each counselor their daily risk digest';

    public function handle(GenerateCounselorDigest $generate): int
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $sent = 0;

        $tenantIds = BatchMember::query()->withoutGlobalScopes()
            ->whereIn('status', $occupying)
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            app(TenantContext::class)->run($tenant, function () use ($generate, &$sent): void {
                $counselors = User::query()
                    ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'manage-leads'))
                    ->get();

                foreach ($counselors as $counselor) {
                    try {
                        if ($generate->handle($counselor) !== null) {
                            $sent++;
                        }
                    } catch (Throwable) {
                        // One counselor's failure never aborts the run.
                    }
                }
            });
        }

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
