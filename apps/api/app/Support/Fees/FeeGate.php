<?php

declare(strict_types=1);

namespace App\Support\Fees;

use App\Models\Batch;
use App\Models\User;

/**
 * Central authority for whether a student's fee status permits access to live
 * classes and recordings (PRD §6.8). A stub for P1.5 — the real dunning/soft-
 * block logic lands in P2.3 behind this same interface, so callers (Join,
 * Recordings) don't change.
 */
interface FeeGate
{
    public function allowsLiveAccess(User $student, Batch $batch): bool;
}
