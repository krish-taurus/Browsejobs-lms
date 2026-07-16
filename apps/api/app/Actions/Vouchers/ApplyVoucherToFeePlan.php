<?php

declare(strict_types=1);

namespace App\Actions\Vouchers;

use App\Enums\VoucherIssueStatus;
use App\Models\FeePlan;
use App\Models\User;
use App\Models\VoucherIssue;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Marks a voucher issue redeemed against a fee plan (PRD §5 Stage 3). The
 * discount itself already landed on instalment 1 at plan-creation time; this
 * records the redemption for analytics + usage enforcement. When the voucher
 * disallows stacking, only one issue may apply per fee plan.
 */
final readonly class ApplyVoucherToFeePlan
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(VoucherIssue $issue, FeePlan $feePlan, int $discountPaise, ?User $actor = null): VoucherIssue
    {
        return app(TenantContext::class)->run($feePlan->tenant, function () use ($issue, $feePlan, $discountPaise, $actor): VoucherIssue {
            $voucher = $issue->voucher;

            if ($voucher !== null && ! $voucher->allow_stacking) {
                $already = VoucherIssue::query()
                    ->where('fee_plan_id', $feePlan->id)
                    ->where('status', VoucherIssueStatus::Applied->value)
                    ->where('id', '!=', $issue->id)
                    ->exists();

                if ($already) {
                    throw ValidationException::withMessages(['voucher' => 'This fee plan already has a voucher applied.']);
                }
            }

            $issue->update([
                'status' => VoucherIssueStatus::Applied->value,
                'fee_plan_id' => $feePlan->id,
                'discount_paise' => $discountPaise,
                'applied_at' => now(),
            ]);

            $this->audit->log(
                action: 'voucher.applied',
                target: $issue,
                metadata: ['fee_plan_id' => $feePlan->id, 'discount_paise' => $discountPaise],
                actor: $actor,
            );

            return $issue;
        });
    }
}
