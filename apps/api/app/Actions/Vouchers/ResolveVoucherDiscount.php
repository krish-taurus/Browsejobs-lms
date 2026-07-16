<?php

declare(strict_types=1);

namespace App\Actions\Vouchers;

use App\Enums\VoucherIssueStatus;
use App\Models\Batch;
use App\Models\User;
use App\Models\VoucherIssue;
use App\Support\Tenancy\TenantContext;

/**
 * Resolves the discount (paise) a student's redeemable voucher yields against a
 * paid batch's registration fee (PRD §5 Stage 3 "pre-applied to their payment
 * link"). Respects course scope, expiry and applied status. Amounts are
 * server-owned from config/fees.php. Returns null when nothing applies.
 */
final readonly class ResolveVoucherDiscount
{
    /**
     * @return array{issue: VoucherIssue, discount_paise: int}|null
     */
    public function handle(User $student, Batch $paidBatch, ?string $code = null): ?array
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $paidBatch, $code): ?array {
            $total = (int) config('fees.registration_paise');

            $issue = VoucherIssue::query()
                ->where('user_id', $student->id)
                ->where('status', VoucherIssueStatus::Issued->value)
                ->when($code !== null && $code !== '', fn ($q) => $q->where('code', $code))
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->whereHas('voucher', function ($q) use ($paidBatch) {
                    $q->where('active', true)->where(function ($qq) use ($paidBatch) {
                        $qq->whereNull('course_id')->orWhere('course_id', $paidBatch->course_id);
                    });
                })
                ->with('voucher')
                ->orderBy('id')
                ->first();

            if ($issue === null || $issue->voucher === null) {
                return null;
            }

            $discount = $issue->voucher->computeDiscount($total);
            if ($discount <= 0) {
                return null;
            }

            return ['issue' => $issue, 'discount_paise' => $discount];
        });
    }
}
