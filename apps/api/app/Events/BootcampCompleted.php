<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Batch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a bootcamp batch is completed (PRD §5 Stage 3). The Review-for-
 * Voucher engine listens to auto-request a testimonial from each attendee.
 */
final class BootcampCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Batch $bootcamp,
    ) {}
}
