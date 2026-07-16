<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\BatchMemberStatus;
use App\Enums\EnrolmentType;
use App\Enums\ProductKind;
use App\Enums\PurchaseStatus;
use App\Events\ProductPurchased;
use App\Jobs\RenderPurchaseReceipt;
use App\Models\BatchMember;
use App\Models\ProductPurchase;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Entitlements\EntitlementService;
use App\Support\Money\GstCalculator;
use App\Support\Tenancy\TenantContext;

/**
 * Settles a captured purchase (PRD §6.17): stamps the inclusive-GST breakup +
 * receipt number, grants the product (credits / access / subscription cycle /
 * live upgrade), audits, and queues the receipt render. Idempotent — a duplicate
 * capture webhook is a no-op.
 */
final readonly class SettlePurchase
{
    public function __construct(
        private EntitlementService $entitlements,
        private AuditLogger $audit,
    ) {}

    public function handle(ProductPurchase $purchase, ?string $razorpayPaymentId = null): ProductPurchase
    {
        return app(TenantContext::class)->run($purchase->tenant, function () use ($purchase, $razorpayPaymentId): ProductPurchase {
            if ($purchase->status === PurchaseStatus::Captured) {
                return $purchase;
            }

            $gst = GstCalculator::inclusive($purchase->amount_paise, (int) config('fees.gst_rate_bps', 1800));

            $purchase->update([
                'status' => PurchaseStatus::Captured->value,
                'captured_at' => now(),
                'razorpay_payment_id' => $razorpayPaymentId ?? $purchase->razorpay_payment_id,
                'taxable_paise' => $gst->taxablePaise,
                'cgst_paise' => $gst->cgstPaise,
                'sgst_paise' => $gst->sgstPaise,
                'total_paise' => $gst->totalPaise,
                'receipt_number' => $this->nextReceiptNumber($purchase->tenant_id),
            ]);

            $this->grant($purchase->fresh());

            $this->audit->log('store.purchase_captured', $purchase, [
                'sku' => $purchase->sku, 'amount_paise' => $purchase->amount_paise, 'receipt' => $purchase->receipt_number,
            ]);

            ProductPurchased::dispatch($purchase->fresh());
            RenderPurchaseReceipt::dispatch($purchase->id);

            return $purchase->fresh();
        });
    }

    private function grant(ProductPurchase $purchase): void
    {
        $user = $purchase->student;

        match ($purchase->kind) {
            ProductKind::Pack => $this->entitlements->grantCredits(
                $user, (string) $purchase->feature, (int) ($purchase->product?->grant_amount ?? 0), "purchase:{$purchase->sku}", $purchase,
            ),
            ProductKind::Subscription => $this->entitlements->grantEntitlement(
                $user, 'career_plus', 'career_plus',
                now()->addDays((int) ($purchase->product?->period_days ?? config('monetization.career_plus.period_days', 30))),
                $purchase,
            ),
            ProductKind::SelfPaced => $this->grantSelfPaced($purchase, $user),
            ProductKind::Upgrade => $this->grantUpgrade($purchase, $user),
        };
    }

    private function grantSelfPaced(ProductPurchase $purchase, User $user): void
    {
        $batchId = (int) ($purchase->product?->source_batch_id ?? 0);
        if ($batchId === 0) {
            return;
        }

        BatchMember::query()->updateOrCreate(
            ['tenant_id' => $user->tenant_id, 'batch_id' => $batchId, 'user_id' => $user->id],
            ['status' => BatchMemberStatus::Enrolled->value, 'enrolment_type' => EnrolmentType::SelfPaced->value, 'enrolled_at' => now()],
        );

        $this->entitlements->grantEntitlement($user, "self_paced:{$batchId}", 'self_paced', null, $purchase);
        $this->entitlements->grantIncludedQuota($user, EnrolmentType::SelfPaced);
    }

    private function grantUpgrade(ProductPurchase $purchase, User $user): void
    {
        $batchId = (int) (($purchase->meta['batch_id'] ?? 0));
        if ($batchId === 0) {
            return;
        }

        BatchMember::query()
            ->where('batch_id', $batchId)->where('user_id', $user->id)
            ->update(['enrolment_type' => EnrolmentType::Live->value]);

        $this->entitlements->grantEntitlement($user, "live:{$batchId}", 'live', null, $purchase);
    }

    private function nextReceiptNumber(int $tenantId): string
    {
        $seq = ProductPurchase::query()->where('tenant_id', $tenantId)->whereNotNull('receipt_number')->count() + 1;

        return 'BJ-P/'.now()->format('Y').'/'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
