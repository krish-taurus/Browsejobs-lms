<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\BatchMemberStatus;
use App\Jobs\GenerateWeeklyReportJob;
use App\Models\BatchMember;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Queues a weekly report for every active student (PRD §6.10). Mirrors
 * RecomputeAllScores' per-tenant loop; dispatching per student keeps each AI call
 * isolated + retry-safe.
 *
 * @phpstan-type WeeklySummary array{queued: int}
 */
final readonly class RunWeeklyReports
{
    /**
     * @return WeeklySummary
     */
    public function handle(): array
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $queued = 0;

        $tenantIds = BatchMember::query()->withoutGlobalScopes()
            ->whereIn('status', $occupying)
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            app(TenantContext::class)->run($tenant, function () use ($occupying, &$queued): void {
                $userIds = BatchMember::query()->whereIn('status', $occupying)->pluck('user_id')->unique();

                User::query()
                    ->whereIn('id', $userIds)
                    ->where('user_type', 'student')
                    ->pluck('id')
                    ->each(function (int $id) use (&$queued): void {
                        GenerateWeeklyReportJob::dispatch($id);
                        $queued++;
                    });
            });
        }

        return ['queued' => $queued];
    }
}
