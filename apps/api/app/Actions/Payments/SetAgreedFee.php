<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\FeePlan;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets what a student actually agreed to pay, when it differs from the course
 * price — "the fee is ₹30,000 but we settled at ₹25,000".
 *
 * The course price stays on the plan as `total_paise` and the difference is
 * recorded as a discount, so the books keep both numbers: list price and what
 * was really charged. Anything already paid is honoured, and only the unpaid
 * instalments are re-cut to add up to the new balance.
 */
final readonly class SetAgreedFee
{
    public function __construct(
        private RestructureFeePlan $restructure,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  int  $agreedPaise  the total the student will pay, all instalments together
     * @return array{agreed_paise: int, discount_paise: int, already_paid_paise: int, remaining_paise: int, instalments: int}
     */
    public function handle(
        FeePlan $plan,
        int $agreedPaise,
        int $count,
        ?Carbon $firstDueOn = null,
        ?string $reason = null,
        ?User $actor = null,
    ): array {
        if ($plan->status !== FeePlanStatus::Active) {
            throw ValidationException::withMessages(['plan' => 'Only an active fee plan can be re-priced.']);
        }

        if ($agreedPaise <= 0) {
            throw ValidationException::withMessages(['amount' => 'The agreed fee must be more than zero.']);
        }

        if ($agreedPaise > $plan->total_paise) {
            throw ValidationException::withMessages([
                'amount' => 'The agreed fee cannot exceed the course price of ₹'.number_format($plan->total_paise / 100).'.',
            ]);
        }

        $plan->loadMissing('instalments');
        $paidPaise = (int) $plan->instalments->where('status', InstalmentStatus::Paid)->sum('amount_paise');

        if ($agreedPaise < $paidPaise) {
            throw ValidationException::withMessages([
                'amount' => 'They have already paid ₹'.number_format($paidPaise / 100).' — the agreed fee cannot be less than that.',
            ]);
        }

        $remaining = $agreedPaise - $paidPaise;

        return DB::transaction(function () use ($plan, $agreedPaise, $paidPaise, $remaining, $count, $firstDueOn, $reason, $actor): array {
            // The course price is untouched; the gap becomes the discount, so a
            // report can still tell list price from what was actually charged.
            $plan->forceFill(['discount_paise' => $plan->total_paise - $agreedPaise])->save();

            $instalments = 0;

            if ($remaining > 0) {
                $result = $this->restructure->handle($plan->fresh('instalments'), $count, $firstDueOn, $actor, $remaining);
                $instalments = $result['created'];
            } else {
                // Fully covered by what they have already paid: clear the rest.
                $plan->instalments()->where('status', '!=', InstalmentStatus::Paid->value)->delete();
            }

            $this->audit->log(
                action: 'fees.agreed_amount_set',
                target: $plan,
                metadata: [
                    'agreed_paise' => $agreedPaise,
                    'course_price_paise' => $plan->total_paise,
                    'discount_paise' => $plan->total_paise - $agreedPaise,
                    'already_paid_paise' => $paidPaise,
                    'remaining_paise' => $remaining,
                    'reason' => $reason,
                ],
                actor: $actor,
            );

            return [
                'agreed_paise' => $agreedPaise,
                'discount_paise' => $plan->total_paise - $agreedPaise,
                'already_paid_paise' => $paidPaise,
                'remaining_paise' => $remaining,
                'instalments' => $instalments,
            ];
        });
    }
}
