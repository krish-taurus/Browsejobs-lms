<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\InstalmentStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Models\Instalment;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

/**
 * Reconciles a Razorpay webhook against the ledger (PRD §6.8). The webhook is
 * the source of truth. Idempotent: a duplicate `payment.captured` for a
 * payment already captured is a no-op (unique `razorpay_payment_id` + status
 * guard). Runs with no tenant context (public route), so it matches the
 * instalment unscoped and does all writes inside the instalment's tenant.
 */
final readonly class ReconcilePayment
{
    public function __construct(private MarkInstalmentPaid $markPaid) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?Payment
    {
        $event = (string) ($payload['event'] ?? '');
        $paymentEntity = (array) data_get($payload, 'payload.payment.entity', []);
        $linkEntity = (array) data_get($payload, 'payload.payment_link.entity', []);

        $razorpayPaymentId = is_string($paymentEntity['id'] ?? null) ? $paymentEntity['id'] : null;
        $orderId = is_string($paymentEntity['order_id'] ?? null) ? $paymentEntity['order_id'] : null;
        $linkId = is_string($linkEntity['id'] ?? null) ? $linkEntity['id'] : null;

        $instalment = $this->matchInstalment($orderId, $linkId);
        if ($instalment === null) {
            return null;
        }

        $tenant = Tenant::query()->find($instalment->tenant_id);
        if ($tenant === null) {
            return null;
        }

        return app(TenantContext::class)->run($tenant, function () use ($event, $instalment, $paymentEntity, $razorpayPaymentId, $orderId): ?Payment {
            if ($razorpayPaymentId !== null) {
                $already = Payment::query()->where('razorpay_payment_id', $razorpayPaymentId)->first();
                if ($already !== null && $already->status === PaymentStatus::Captured) {
                    return $already; // idempotent: already reconciled.
                }
            }

            $captured = in_array($event, ['payment.captured', 'order.paid', 'payment_link.paid'], true);
            $failed = $event === 'payment.failed';
            if (! $captured && ! $failed) {
                return null;
            }

            $payment = Payment::query()
                ->where('instalment_id', $instalment->id)
                ->when($orderId !== null, fn ($q) => $q->where('razorpay_order_id', $orderId))
                ->orderByDesc('id')
                ->first()
                ?? Payment::query()->create([
                    'tenant_id' => $instalment->tenant_id,
                    'fee_plan_id' => $instalment->fee_plan_id,
                    'instalment_id' => $instalment->id,
                    'razorpay_order_id' => $orderId,
                    'amount_paise' => $instalment->amount_paise,
                    'status' => PaymentStatus::Created->value,
                ]);

            $payment->razorpay_payment_id = $razorpayPaymentId ?? $payment->razorpay_payment_id;
            $payment->method = is_string($paymentEntity['method'] ?? null) ? $paymentEntity['method'] : $payment->method;
            $payment->raw = $paymentEntity;

            if ($captured) {
                $payment->status = PaymentStatus::Captured;
                $payment->captured_at = now();
                $payment->save();

                $this->markPaid->handle($instalment, $payment);
            } else {
                $payment->status = PaymentStatus::Failed;
                $payment->save();

                $instalment->status = InstalmentStatus::Failed;
                $instalment->save();

                PaymentFailed::dispatch($payment, $instalment);
            }

            return $payment;
        });
    }

    private function matchInstalment(?string $orderId, ?string $linkId): ?Instalment
    {
        if ($orderId !== null && $orderId !== '') {
            $byOrder = Instalment::query()->withoutGlobalScopes()->where('razorpay_order_id', $orderId)->first();
            if ($byOrder !== null) {
                return $byOrder;
            }
        }

        if ($linkId !== null && $linkId !== '') {
            return Instalment::query()->withoutGlobalScopes()->where('razorpay_payment_link_id', $linkId)->first();
        }

        return null;
    }
}
