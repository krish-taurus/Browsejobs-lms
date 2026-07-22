<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Batch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a masterclass batch is marked complete (Platform Spec §3 review
 * ladder, stage 1). The review engine listens to request a review from each
 * attendee.
 */
final class MasterclassCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Batch $masterclass,
    ) {}
}
