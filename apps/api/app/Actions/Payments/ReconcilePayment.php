<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\InstalmentStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Models\Instalment;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Razorpay\RazorpayClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciles a Razorpay webhook against the ledger (PRD §6.8). The webhook is
 * the source of truth. Idempotent: a duplicate `payment.captured` for a
 * payment already captured is a no-op (unique `razorpay_payment_id` + status
 * guard). Runs with no tenant context (public route), so it matches the
 * instalment unscoped and does all writes inside the instalment's tenant.
 */
final readonly class ReconcilePayment
{
    public function __construct(
        private MarkInstalmentPaid $markPaid,
        private RazorpayClient $razorpay,
    ) {}

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
            $byLink = Instalment::query()->withoutGlobalScopes()->where('razorpay_payment_link_id', $linkId)->first();
            if ($byLink !== null) {
                return $byLink;
            }
        }

        return $this->matchByOrderReceipt($orderId);
    }

    /**
     * Last resort: ask Razorpay who the order belongs to.
     *
     * A payment made through a payment link fires `payment.captured` carrying
     * only an order id — the link id appears solely on `payment_link.paid`. If
     * that event is not subscribed on the webhook, a real payment would sit
     * unreconciled until someone pressed Sync. The order's `receipt` is the
     * reference we set when creating the link ("inst-<id>"), so one lookup
     * recovers the instalment and the CRM updates the moment money lands,
     * whatever the webhook happens to be configured with.
     */
    private function matchByOrderReceipt(?string $orderId): ?Instalment
    {
        if ($orderId === null || $orderId === '') {
            return null;
        }

        try {
            $order = $this->razorpay->fetchOrder($orderId);
        } catch (Throwable $e) {
            Log::warning('Could not fetch Razorpay order while reconciling.', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! preg_match('/^inst-(\d+)/', (string) ($order['receipt'] ?? ''), $m)) {
            return null;
        }

        $instalment = Instalment::query()->withoutGlobalScopes()->find((int) $m[1]);

        // Remember the order id so the next event on this instalment matches
        // directly, without another API call.
        if ($instalment !== null && $instalment->razorpay_order_id === null) {
            $instalment->forceFill(['razorpay_order_id' => $orderId])->save();
        }

        return $instalment;
    }
}
