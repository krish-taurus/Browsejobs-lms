<?php

declare(strict_types=1);

namespace App\Support\Razorpay;

/**
 * In-memory Razorpay client for tests and local dev without real credentials.
 * Records calls and returns deterministic ids/urls.
 */
final class FakeRazorpayClient implements RazorpayClient
{
    /** @var list<array<string, mixed>> */
    public array $orders = [];

    /** @var list<array<string, mixed>> */
    public array $links = [];

    private int $sequence = 0;

    public function createOrder(int $amountPaise, string $receipt, array $notes = []): RazorpayOrder
    {
        $this->sequence++;
        $id = 'order_TEST'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT);
        $this->orders[] = ['id' => $id, 'amount' => $amountPaise, 'receipt' => $receipt, 'notes' => $notes];

        return new RazorpayOrder(id: $id, amountPaise: $amountPaise);
    }

    public function createPaymentLink(int $amountPaise, string $description, array $customer = [], string $reference = ''): RazorpayPaymentLink
    {
        $this->sequence++;
        $id = 'plink_TEST'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT);
        $this->links[] = ['id' => $id, 'amount' => $amountPaise, 'description' => $description, 'reference' => $reference];

        return new RazorpayPaymentLink(id: $id, url: "https://rzp.test/l/{$id}");
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        return $signature === 'valid-signature';
    }
}
