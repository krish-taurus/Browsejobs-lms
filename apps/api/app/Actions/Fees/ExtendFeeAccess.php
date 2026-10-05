<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\AccessBlock;
use App\Models\FeePlan;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buys a student more time before the fee lock bites — "my salary comes on the
 * 5th" handled without writing the fee off.
 *
 * The extension is a dated field on the plan, not a silent lift of the block, so
 * the reason and the deadline are both on record: the dunning ladder honours it,
 * any block already applied is lifted immediately, and the lock reapplies by
 * itself the day after it expires. Nothing else about the debt changes.
 */
final readonly class ExtendFeeAccess
{
    /** A reprieve, not an amnesty. */
    public const MAX_DAYS = 30;

    public function __construct(private AuditLogger $audit) {}

    public function handle(FeePlan $plan, int $days, ?string $reason = null, ?User $actor = null): Carbon
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw ValidationException::withMessages([
                'days' => 'Choose between 1 and '.self::MAX_DAYS.' days.',
            ]);
        }

        // Extend from today, or from the existing extension when one is still
        // running, so two grants in a row add up instead of overwriting.
        $from = $plan->accessExtensionActive() ? $plan->access_extended_until->copy() : Carbon::today();
        $until = $from->addDays($days);

        return DB::transaction(function () use ($plan, $until, $reason, $actor, $days): Carbon {
            $plan->forceFill([
                'access_extended_until' => $until->toDateString(),
                'access_extended_reason' => $reason,
            ])->save();

            $lifted = AccessBlock::query()->withoutGlobalScopes()
                ->where('user_id', $plan->user_id)
                ->whereNull('lifted_at')
                ->update([
                    'lifted_at' => now(),
                    'lifted_reason' => 'Access extended to '.$until->toDateString(),
                    'updated_at' => now(),
                ]);

            $this->audit->log(
                action: 'fees.access_extended',
                target: $plan,
                metadata: [
                    'days' => $days,
                    'until' => $until->toDateString(),
                    'reason' => $reason,
                    'blocks_lifted' => $lifted,
                ],
                actor: $actor,
            );

            return $until;
        });
    }
}
