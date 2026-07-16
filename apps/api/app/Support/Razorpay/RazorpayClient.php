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
}
