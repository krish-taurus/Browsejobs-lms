<?php

declare(strict_types=1);

namespace App\Actions\Scoring;

use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Nightly rule-based rescore of every active student (PRD §6.4), run by
 * `scores:recompute`. The disengaged never open the Coach Panel, and they are the
 * highest risk — so the batch keeps `student_scores` fresh for the counselor
 * dashboard. Idempotent; mirrors RunDunningLadder's per-tenant loop.
 *
 * @phpstan-type RecomputeSummary array{scored: int}
 */
final readonly class RecomputeAllScores
{
    public function __construct(private ComputeStudentScores $compute) {}

    /**
     * @return RecomputeSummary
     */
    public function handle(): array
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $scored = 0;

        $tenantIds = BatchMember::query()->withoutGlobalScopes()
            ->whereIn('status', $occupying)
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            app(TenantContext::class)->run($tenant, function () use ($occupying, &$scored): void {
                $userIds = BatchMember::query()->whereIn('status', $occupying)->pluck('user_id')->unique();

                User::query()
                    ->whereIn('id', $userIds)
                    ->where('user_type', 'student')
                    ->get()
                    ->each(function (User $student) use (&$scored): void {
                        $this->compute->handle($student);
                        $scored++;
                    });
            });
        }

        return ['scored' => $scored];
    }
}
