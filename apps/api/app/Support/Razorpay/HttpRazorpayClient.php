<?php

declare(strict_types=1);

namespace App\Support\Razorpay;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Real Razorpay client (Basic auth: key_id:key_secret). Only invoked from
 * Actions/jobs; unit tests use {@see FakeRazorpayClient}.
 */
final class HttpRazorpayClient implements RazorpayClient
{
    /**
     * @param  array{key_id: string, key_secret: string, webhook_secret: string, base_url: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function createOrder(int $amountPaise, string $receipt, array $notes = []): RazorpayOrder
    {
        $response = $this->request()->post($this->config['base_url'].'/orders', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => $notes,
        ])->throw()->json();

        return new RazorpayOrder(
            id: (string) $response['id'],
            amountPaise: (int) $response['amount'],
        );
    }

    public function createPaymentLink(int $amountPaise, string $description, array $customer = [], string $reference = ''): RazorpayPaymentLink
    {
        $response = $this->request()->post($this->config['base_url'].'/payment_links', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'description' => $description,
            'reference_id' => $reference,
            'customer' => $customer,
            'notify' => ['sms' => false, 'email' => false],
        ])->throw()->json();

        return new RazorpayPaymentLink(
            id: (string) $response['id'],
            url: (string) $response['short_url'],
        );
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', "{$orderId}|{$paymentId}", $this->config['key_secret']);

        return hash_equals($expected, $signature);
    }

    public function refund(string $paymentId, int $amountPaise): string
    {
        $response = $this->request()->post($this->config['base_url']."/payments/{$paymentId}/refund", [
            'amount' => $amountPaise,
        ])->throw()->json();

        return (string) $response['id'];
    }

    public function createSubscription(int $amountPaise, int $periodDays, array $notes = []): RazorpaySubscription
    {
        // A real integration first creates a Plan for (amount, period) then a
        // Subscription against it; kept behind this interface for the app layer.
        $response = $this->request()->post($this->config['base_url'].'/subscriptions', [
            'total_count' => 120,
            'notes' => $notes,
        ])->throw()->json();

        return new RazorpaySubscription(
            id: (string) $response['id'],
            status: (string) ($response['status'] ?? 'active'),
        );
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->request()->post($this->config['base_url']."/subscriptions/{$subscriptionId}/cancel", [
            'cancel_at_cycle_end' => 1,
        ])->throw();
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->config['key_id'], $this->config['key_secret'])->acceptJson();
    }
}
