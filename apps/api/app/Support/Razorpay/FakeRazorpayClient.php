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

    /**
     * Link state a test wants `fetchPaymentLink` to report, keyed by link id.
     *
     * @var array<string, array{status: string, order_id?: string|null, payment_id?: string|null, method?: string|null}>
     */
    public array $linkStatuses = [];

    /** @var list<string> payment links cancelled in this test run */
    public array $cancelledLinks = [];

    public function fetchOrder(string $orderId): array
    {
        // Tests may seed $orders keyed by id; createOrder() appends numerically.
        $found = $this->orders[$orderId] ?? null;

        if ($found === null) {
            foreach ($this->orders as $order) {
                if (is_array($order) && ($order['id'] ?? null) === $orderId) {
                    $found = $order;
                    break;
                }
            }
        }

        return [
            'receipt' => $found['receipt'] ?? null,
            'amount_paise' => isset($found['amount']) ? (int) $found['amount'] : ($found['amount_paise'] ?? null),
            'status' => $found['status'] ?? null,
        ];
    }

    public function fetchPaymentLink(string $paymentLinkId): array
    {
        $state = $this->linkStatuses[$paymentLinkId] ?? ['status' => 'created'];

        return [
            'status' => (string) $state['status'],
            'amount_paise' => $state['amount_paise'] ?? null,
            'order_id' => $state['order_id'] ?? null,
            'payment_id' => $state['payment_id'] ?? null,
            'method' => $state['method'] ?? null,
        ];
    }

    public function cancelPaymentLink(string $paymentLinkId): void
    {
        $this->cancelledLinks[] = $paymentLinkId;
    }
}
