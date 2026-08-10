<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\ReconcilePayment;
use App\Enums\InstalmentStatus;
use App\Models\Instalment;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polls Razorpay for pending instalments that carry a payment link and settles
 * the ones Razorpay reports paid — through the same ReconcilePayment path the
 * webhook uses (idempotent). This is the safety net for missed webhooks and
 * the only confirmation path on localhost, where webhooks cannot arrive.
 */
final class ReconcilePaymentLinks extends Command
{
    protected $signature = 'fees:reconcile-links {--limit=50 : Max links to check per run}';

    protected $description = 'Settle pending instalments whose Razorpay payment links are paid';

    public function handle(RazorpayClient $razorpay, ReconcilePayment $reconcile): int
    {
        $pending = Instalment::query()->withoutGlobalScopes()
            ->where('status', '!=', InstalmentStatus::Paid->value)
            ->whereNotNull('razorpay_payment_link_id')
            ->orderBy('due_on')
            ->limit((int) $this->option('limit'))
            ->get();

        $settled = 0;

        foreach ($pending as $instalment) {
            try {
                $link = $razorpay->fetchPaymentLink((string) $instalment->razorpay_payment_link_id);
            } catch (Throwable $e) {
                $this->warn("Instalment {$instalment->id}: Razorpay fetch failed — {$e->getMessage()}");

                continue;
            }

            if ($link['status'] !== 'paid') {
                continue;
            }

            $payment = $reconcile->handle([
                'event' => 'payment_link.paid',
                'payload' => [
                    'payment_link' => ['entity' => ['id' => $instalment->razorpay_payment_link_id]],
                    'payment' => ['entity' => array_filter([
                        'id' => $link['payment_id'],
                        'order_id' => $link['order_id'],
                        'method' => $link['method'],
                    ])],
                ],
            ]);

            if ($payment !== null) {
                $settled++;
                $this->info("Instalment {$instalment->id} settled (payment {$payment->razorpay_payment_id}).");
            }
        }

        $this->info("Checked {$pending->count()} pending link(s), settled {$settled}.");

        return self::SUCCESS;
    }
}
