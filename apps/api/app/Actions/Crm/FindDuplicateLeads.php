<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\Lead;
use Illuminate\Support\Collection;

/**
 * Detects duplicate leads by normalized phone within the tenant (PRD §6.12
 * "dedupe by phone number"). Excludes the lead itself and any already-merged
 * tombstones. Feeds the merge tool.
 */
final class FindDuplicateLeads
{
    /**
     * @return Collection<int, Lead>
     */
    public function handle(Lead $lead): Collection
    {
        if ($lead->phone_normalized === null || $lead->phone_normalized === '') {
            return collect();
        }

        return Lead::query()
            ->where('tenant_id', $lead->tenant_id)
            ->where('phone_normalized', $lead->phone_normalized)
            ->where('id', '!=', $lead->id)
            ->whereNull('merged_into_id')
            ->orderBy('id')
            ->get();
    }
}
