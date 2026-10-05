<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\FeePlanType;
use App\Models\Batch;
use App\Models\FeePlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The office-side version of a student choosing their own fee plan
 * ({@see CreateFeePlan}), for the student who paid before ever touching the
 * dashboard — cash at counselling, an upfront bank transfer, money handed to
 * HR. Raises the plan exactly as the student's own "choose a plan" screen
 * would, then immediately settles its first instalment as a manual payment
 * ({@see RecordManualPayment}) — full payment is a single instalment, so
 * that one instalment IS the whole fee; an EMI plan settles just the first,
 * leaving the rest due on schedule same as anyone paying online.
 */
final readonly class OnboardBatchPayment
{
    public function __construct(
        private CreateFeePlan $createPlan,
        private RecordManualPayment $recordPayment,
    ) {}

    public function handle(
        Tenant $tenant,
        User $student,
        Batch $batch,
        FeePlanType $type,
        int $emiCount,
        string $method,
        ?Carbon $paidOn = null,
        ?string $reference = null,
        ?string $proofUrl = null,
        ?User $actor = null,
        ?int $agreedTotalPaise = null,
    ): FeePlan {
        // "This one closed at ₹20,000, not the ₹30,000 list price" — same
        // idea as Set Amount on an existing plan, just applied at the moment
        // the plan is first raised instead of afterwards. The course price
        // itself is never touched; the gap becomes a discount so a report
        // can still tell list price from what was actually agreed.
        $coursePrice = CreateFeePlan::totalFor($batch);
        $discountPaise = $agreedTotalPaise !== null
            ? max(0, min($coursePrice - $agreedTotalPaise, $coursePrice))
            : 0;

        $plan = $this->createPlan->handle(
            tenant: $tenant,
            student: $student,
            batch: $batch,
            type: $type,
            emiCount: $type === FeePlanType::Emi ? $emiCount : 1,
            discountPaise: $discountPaise,
            actor: $actor,
        );

        $first = $plan->instalments->sortBy('seq')->first();

        if ($first !== null) {
            $this->recordPayment->handle(
                instalment: $first,
                method: $method,
                paidOn: $paidOn,
                note: $type === FeePlanType::Single ? 'Full payment — onboarded from batch list' : 'First instalment — onboarded from batch list',
                actor: $actor,
                reference: $reference,
                proofUrl: $proofUrl,
            );
        }

        return $plan->fresh('instalments');
    }
}
