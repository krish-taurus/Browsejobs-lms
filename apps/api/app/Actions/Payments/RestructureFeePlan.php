<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Enums\LedgerDirection;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Re-cuts the UNPAID part of a fee plan into a different number of instalments —
 * the "can I pay in more, smaller EMIs?" request, done without a refund or a new
 * plan. The split is an equal one by default, or the operator's own uneven
 * amounts (₹5,000 then ₹8,000, say) via $customAmountsPaise.
 *
 * The invariants, in order of importance:
 *   1. Paid instalments are never touched, and never re-numbered.
 *   2. The student never ends up owing a different amount: the new rows sum to
 *      exactly what the replaced rows summed to.
 *   3. A live Razorpay link on a replaced instalment is cancelled first, so the
 *      student cannot pay an amount that no longer exists.
 *   4. The ledger stays truthful — removed debits are reversed, new ones written.
 *
 * @phpstan-type RestructureResult array{removed: int, created: int, unpaid_paise: int, links_cancelled: list<string>, links_failed: list<string>}
 */
final readonly class RestructureFeePlan
{
    /** Sanity bounds for a manual re-cut. One instalment is "merge it all back". */
    public const MIN_INSTALMENTS = 1;

    public const MAX_INSTALMENTS = 24;

    public function __construct(
        private RazorpayClient $razorpay,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>|null  $customAmountsPaise  Uneven instalments — "₹5,000
     *                                               then ₹8,000" rather than an
     *                                               equal split. When given, its
     *                                               length is the real instalment
     *                                               count ($count is still
     *                                               validated but otherwise
     *                                               ignored), and it must sum to
     *                                               exactly the outstanding
     *                                               balance — the "never owe a
     *                                               different amount" invariant
     *                                               applies here too, it is just
     *                                               the operator doing the
     *                                               dividing instead of intdiv().
     * @return RestructureResult
     */
    public function handle(FeePlan $plan, int $count, ?Carbon $firstDueOn = null, ?User $actor = null, ?int $targetUnpaidPaise = null, ?array $customAmountsPaise = null): array
    {
        if ($plan->status !== FeePlanStatus::Active) {
            throw ValidationException::withMessages(['plan' => 'Only an active fee plan can be restructured.']);
        }

        if ($customAmountsPaise !== null) {
            $count = count($customAmountsPaise);

            foreach ($customAmountsPaise as $amount) {
                if ($amount <= 0) {
                    throw ValidationException::withMessages(['amounts' => 'Every instalment amount must be greater than zero.']);
                }
            }
        }

        if ($count < self::MIN_INSTALMENTS || $count > self::MAX_INSTALMENTS) {
            throw ValidationException::withMessages([
                'count' => 'Choose between '.self::MIN_INSTALMENTS.' and '.self::MAX_INSTALMENTS.' instalments.',
            ]);
        }

        $plan->loadMissing('instalments');

        $paid = $plan->instalments->where('status', InstalmentStatus::Paid);
        $unpaid = $plan->instalments->where('status', '!=', InstalmentStatus::Paid)->sortBy('seq')->values();

        if ($unpaid->isEmpty()) {
            throw ValidationException::withMessages(['plan' => 'This plan is fully paid — there is nothing left to reschedule.']);
        }

        // When the agreed fee itself changed, the caller passes the new balance;
        // otherwise the same money is simply spread differently.
        $unpaidPaise = $targetUnpaidPaise ?? (int) $unpaid->sum('amount_paise');

        if ($unpaidPaise <= 0) {
            throw ValidationException::withMessages(['plan' => 'The outstanding balance is zero — nothing to reschedule.']);
        }

        if ($customAmountsPaise !== null && array_sum($customAmountsPaise) !== $unpaidPaise) {
            $given = number_format(array_sum($customAmountsPaise) / 100, 2);
            $need = number_format($unpaidPaise / 100, 2);

            throw ValidationException::withMessages([
                'amounts' => "Those instalments add up to ₹{$given}, but the outstanding balance is ₹{$need}. They must add up to exactly that.",
            ]);
        }

        // Cancel live links BEFORE the rows go away: if Razorpay refuses we still
        // know which link is loose, and the operator gets told about it.
        $cancelled = [];
        $failed = [];

        foreach ($unpaid as $instalment) {
            if ($instalment->razorpay_payment_link_id === null) {
                continue;
            }

            try {
                $this->razorpay->cancelPaymentLink($instalment->razorpay_payment_link_id);
                $cancelled[] = $instalment->razorpay_payment_link_id;
            } catch (Throwable $e) {
                $failed[] = $instalment->razorpay_payment_link_id;
                Log::warning('Could not cancel Razorpay payment link during fee restructure.', [
                    'fee_plan_id' => $plan->id,
                    'payment_link_id' => $instalment->razorpay_payment_link_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $start = $firstDueOn?->copy() ?? Carbon::today();
        $nextSeq = ((int) $paid->max('seq')) + 1;

        return DB::transaction(function () use ($plan, $unpaid, $unpaidPaise, $count, $start, $nextSeq, $actor, $cancelled, $failed, $customAmountsPaise): array {
            foreach ($unpaid as $instalment) {
                LedgerEntry::query()->create([
                    'tenant_id' => $plan->tenant_id,
                    'user_id' => $plan->user_id,
                    'fee_plan_id' => $plan->id,
                    'direction' => LedgerDirection::Credit->value,
                    'amount_paise' => $instalment->amount_paise,
                    'description' => "Instalment {$instalment->seq} cancelled (EMI plan changed)",
                    'occurred_at' => now(),
                ]);

                $instalment->delete();
            }

            // Even split, remainder onto the FIRST new instalment, mirroring
            // PreviewSchedule so both paths round the same way — unless the
            // operator handed over their own amounts, in which case those are
            // the split (already checked above to sum to $unpaidPaise exactly).
            $base = $customAmountsPaise === null ? intdiv($unpaidPaise, $count) : 0;
            $created = 0;

            for ($i = 0; $i < $count; $i++) {
                $amount = $customAmountsPaise[$i] ?? $base + ($i === 0 ? $unpaidPaise - $base * $count : 0);
                $seq = $nextSeq + $i;

                Instalment::query()->create([
                    'tenant_id' => $plan->tenant_id,
                    'fee_plan_id' => $plan->id,
                    'seq' => $seq,
                    'amount_paise' => $amount,
                    'due_on' => $start->copy()->addMonthsNoOverflow($i)->toDateString(),
                    'status' => InstalmentStatus::Pending->value,
                ]);

                LedgerEntry::query()->create([
                    'tenant_id' => $plan->tenant_id,
                    'user_id' => $plan->user_id,
                    'fee_plan_id' => $plan->id,
                    'direction' => LedgerDirection::Debit->value,
                    'amount_paise' => $amount,
                    'description' => "Instalment {$seq} due",
                    'occurred_at' => now(),
                ]);

                $created++;
            }

            $this->audit->log(
                action: 'fees.plan_restructured',
                target: $plan,
                metadata: [
                    'removed' => $unpaid->count(),
                    'created' => $created,
                    'unpaid_paise' => $unpaidPaise,
                    'first_due_on' => $start->toDateString(),
                    'custom_amounts_paise' => $customAmountsPaise,
                    'links_cancelled' => $cancelled,
                    'links_failed' => $failed,
                ],
                actor: $actor,
            );

            return [
                'removed' => $unpaid->count(),
                'created' => $created,
                'unpaid_paise' => $unpaidPaise,
                'links_cancelled' => $cancelled,
                'links_failed' => $failed,
            ];
        });
    }
}
