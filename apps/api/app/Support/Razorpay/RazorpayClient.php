<?php

declare(strict_types=1);

namespace App\Support\Razorpay;

/**
 * Razorpay REST API (Orders + Payment Links). Every implementation is called
 * only from Actions/queued jobs (CLAUDE.md: external API calls are queued/mocked
 * in tests). Tests bind {@see FakeRazorpayClient}. UPI AutoPay/eMandate creation
 * will extend this interface in a later slice.
 */
interface RazorpayClient
{
    /**
     * Create an order for a Razorpay Checkout (amount in paise).
     *
     * @param  array<string, string>  $notes
     */
    public function createOrder(int $amountPaise, string $receipt, array $notes = []): RazorpayOrder;

    /**
     * Create a personalized (hosted) payment link for an amount in paise.
     *
     * @param  array<string, string>  $customer  keys: name, email, contact
     */
    public function createPaymentLink(int $amountPaise, string $description, array $customer = [], string $reference = ''): RazorpayPaymentLink;

    /**
     * Verify the Razorpay Checkout handshake signature
     * (HMAC-SHA256("{orderId}|{paymentId}", key_secret)).
     */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool;

    /**
     * Refund a captured payment (amount in paise). Returns the refund id.
     */
    public function refund(string $paymentId, int $amountPaise): string;

    /**
     * Create a recurring subscription (Career+, PRD §6.17). `amountPaise` per cycle
     * of `periodDays`.
     *
     * @param  array<string, string>  $notes
     */
    public function createSubscription(int $amountPaise, int $periodDays, array $notes = []): RazorpaySubscription;

    /**
     * Cancel a subscription (at cycle end).
     */
    public function cancelSubscription(string $subscriptionId): void;

    /**
     * Read an order, mainly for its `receipt` — payment links carry the
     * reference_id we set ("inst-<id>") through to the order, which is how a
     * bare payment.captured webhook can still be traced back to an instalment.
     *
     * @return array{receipt: string|null, amount_paise: int|null, status: string|null}
     */
    public function fetchOrder(string $orderId): array;

    /**
     * Read a payment link's current state, so a link that was paid while its
     * webhook went missing can still be settled (`fees:reconcile-links`).
     *
     * @return array{status: string, amount_paise: int|null, order_id: string|null, payment_id: string|null, method: string|null}
     */
    public function fetchPaymentLink(string $paymentLinkId): array;

    /**
     * Cancel an unpaid payment link so it can no longer be paid. Needed when an
     * instalment is re-cut (EMI plan changed): the old link is for an amount that
     * no longer exists, and a student paying it would send money against a row
     * that is gone. Already-paid links cannot be cancelled — Razorpay rejects it.
     */
    public function cancelPaymentLink(string $paymentLinkId): void;
}
