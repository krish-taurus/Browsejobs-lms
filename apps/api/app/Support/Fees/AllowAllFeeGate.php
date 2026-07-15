<?php

declare(strict_types=1);

namespace App\Support\Fees;

use App\Models\Batch;
use App\Models\User;

/**
 * P1.5 stub: fees aren't modelled yet, so access is always allowed. Replaced by
 * the real FeeGate implementation in P2.3.
 */
final class AllowAllFeeGate implements FeeGate
{
    public function allowsLiveAccess(User $student, Batch $batch): bool
    {
        return true;
    }
}
