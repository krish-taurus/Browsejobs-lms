<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\BatchMemberStatus;
use App\Enums\ProductKind;
use App\Enums\PurchaseStatus;
use App\Models\BatchMember;
use App\Models\ProductPurchase;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Entitlements\EntitlementService;
use App\Support\Razorpay\RazorpayClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Refunds a captured purchase (PRD §6.17). Founder policy: claw back unspent
 * credits, revoke access/subscription entitlements, and un-enrol self-paced.
 * Idempotent — a refunded purchase is untouched.
 */
final readonly class RefundPurchase
{
    public function __construct(
        private RazorpayClient $razorpay,
        private EntitlementService $entitlements,
        private AuditLogger $audit,
    ) {}

    public function handle(ProductPurchase $purchase, string $reason, ?User $actor = null): ProductPurchase
    {
        return app(TenantContext::class)->run($purchase->tenant, function () use ($purchase, $reason, $actor): ProductPurchase {
            if ($purchase->status !== PurchaseStatus::Captured) {
                throw ValidationException::withMessages(['purchase' => 'Only a captured purchase can be refunded.']);
            }

            if ($purchase->razorpay_payment_id !== null) {
                $this->razorpay->refund($purchase->razorpay_payment_id, $purchase->amount_paise);
            }

            $this->reverse($purchase);

            $purchase->update([
                'status' => PurchaseStatus::Refunded->value,
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ]);

            $this->audit->log('store.purchase_refunded', $purchase, ['reason' => $reason, 'amount_paise' => $purchase->amount_paise], $actor);

            return $purchase->fresh();
        });
    }

    private function reverse(ProductPurchase $purchase): void
    {
        $user = $purchase->student;

        match ($purchase->kind) {
            ProductKind::Pack => $this->entitlements->clawbackCredits(
                $user, (string) $purchase->feature, (int) ($purchase->product?->grant_amount ?? 0), "refund:{$purchase->sku}",
            ),
            ProductKind::Subscription => $this->entitlements->revokeEntitlement($user, 'career_plus'),
            ProductKind::SelfPaced => $this->reverseSelfPaced($purchase, $user),
            ProductKind::Upgrade => null,
        };
    }

    private function reverseSelfPaced(ProductPurchase $purchase, User $user): void
    {
        $batchId = (int) ($purchase->product?->source_batch_id ?? 0);
        if ($batchId === 0) {
            return;
        }

        $this->entitlements->revokeEntitlement($user, "self_paced:{$batchId}");
        BatchMember::query()
            ->where('batch_id', $batchId)->where('user_id', $user->id)
            ->update(['status' => BatchMemberStatus::Dropped->value]);
    }
}
