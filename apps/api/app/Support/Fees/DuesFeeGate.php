<?php

declare(strict_types=1);

namespace App\Support\Fees;

use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\User;

/**
 * The real fee gate (P2.3, PRD §6.8). Denies live-class / recordings access when
 * the student has an active access block (soft or hard). A student with cleared
 * fees — or no fee plan at all — has no active block and passes. Replaces
 * AllowAllFeeGate.
 */
final class DuesFeeGate implements FeeGate
{
    public function allowsLiveAccess(User $student, Batch $batch): bool
    {
        return ! AccessBlock::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $student->tenant_id)
            ->where('user_id', $student->id)
            ->whereNull('lifted_at')
            ->exists();
    }
}
