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

    /** @var list<array<string, mixed>> */
    public array $refunds = [];

    /** @var list<array<string, mixed>> */
    public array $subscriptions = [];

    /** @var list<string> */
    public array $cancelled = [];

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

    /** @var array<string, array{status: string, order_id: string|null, payment_id: string|null, method: string|null}> */
    public array $linkStatuses = [];

    public function fetchPaymentLink(string $linkId): array
    {
        return $this->linkStatuses[$linkId]
            ?? ['status' => 'created', 'order_id' => null, 'payment_id' => null, 'method' => null];
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        return $signature === 'valid-signature';
    }

    public function refund(string $paymentId, int $amountPaise): string
    {
        $this->sequence++;
        $id = 'rfnd_TEST'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT);
        $this->refunds[] = ['id' => $id, 'payment_id' => $paymentId, 'amount' => $amountPaise];

        return $id;
    }

    public function createSubscription(int $amountPaise, int $periodDays, array $notes = []): RazorpaySubscription
    {
        $this->sequence++;
        $id = 'sub_TEST'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT);
        $this->subscriptions[] = ['id' => $id, 'amount' => $amountPaise, 'period_days' => $periodDays, 'notes' => $notes];

        return new RazorpaySubscription(id: $id);
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->cancelled[] = $subscriptionId;
    }
}
