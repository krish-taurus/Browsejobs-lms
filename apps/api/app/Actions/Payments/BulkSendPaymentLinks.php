<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\Batch;
use App\Models\FeePlan;

/**
 * Sends personalized payment links to every active fee plan in a batch (PRD §6.8
 * "admin bulk-send"). Targets each plan's earliest pending instalment that has
 * no link yet. Returns the number of links created.
 */
final readonly class BulkSendPaymentLinks
{
    public function __construct(private SendPaymentLink $send) {}

    public function handle(Batch $batch): int
    {
        $count = 0;

        FeePlan::query()
            ->where('batch_id', $batch->id)
            ->where('status', FeePlanStatus::Active->value)
            ->with('student')
            ->each(function (FeePlan $plan) use (&$count): void {
                $instalment = $plan->instalments()
                    ->where('status', InstalmentStatus::Pending->value)
                    ->whereNull('payment_link_url')
                    ->orderBy('seq')
                    ->first();

                if ($instalment !== null) {
                    $this->send->handle($instalment);
                    $count++;
                }
            });

        return $count;
    }
}
