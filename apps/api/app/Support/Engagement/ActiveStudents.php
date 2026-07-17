<?php

declare(strict_types=1);

namespace App\Support\Engagement;

use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use Illuminate\Support\Collection;

/**
 * Shared audience query for engagement fan-outs: distinct user ids currently
 * occupying any batch of the tenant.
 */
final class ActiveStudents
{
    /**
     * @return Collection<int, int>
     */
    public static function idsFor(int $tenantId, int $limit): Collection
    {
        return BatchMember::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying()))
            ->distinct()
            ->limit($limit)
            ->pluck('user_id');
    }
}
