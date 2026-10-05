<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\InstalmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Instalment;
use App\Models\Payment;
use App\Models\User;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Records money that never touched Razorpay — cash handed to the office, a
 * bank transfer, a cheque, an amount someone paid HR directly. Reuses
 * exactly the same settlement path a real Razorpay capture goes through
 * ({@see MarkInstalmentPaid}): the instalment is marked paid, the ledger
 * entry and GST receipt are written, enrolment flips on instalment 1, and
 * any fee-block on the student is lifted. The only difference from a real
 * capture is that razorpay_payment_id stays null and `method` records how
 * the money actually arrived.
 *
 * An instalment is one indivisible unit here, same as everywhere else in
 * this schema — this settles it in full, at its own amount, not a partial
 * top-up. A student who paid ₹5,000 of an ₹8,000 instalment in cash needs
 * that instalment re-cut to ₹5,000 first (Change EMI plan), then this.
 */
final readonly class RecordManualPayment
{
    /** Free text kept intentionally open — "hr_cash", "referral_waiver", a
     *  branch name — rather than an enum that would need a migration for
     *  every new way money turns out to actually arrive. */
    public function __construct(
        private MarkInstalmentPaid $markPaid,
        private RazorpayClient $razorpay,
    ) {}

    public function handle(
        Instalment $instalment,
        string $method,
        ?Carbon $paidOn = null,
        ?string $note = null,
        ?User $actor = null,
        ?string $reference = null,
        ?string $proofUrl = null,
    ): Payment {
        if ($instalment->status === InstalmentStatus::Paid) {
            throw ValidationException::withMessages(['instalment' => 'This instalment is already marked paid.']);
        }

        if (trim($method) === '') {
            throw ValidationException::withMessages(['method' => 'Say how the payment actually arrived — cash, bank transfer, etc.']);
        }

        // It must not still be payable once the office has already collected
        // this money another way — a student paying the old link afterwards
        // would double-collect against an instalment that is about to close.
        if ($instalment->razorpay_payment_link_id !== null) {
            try {
                $state = $this->razorpay->fetchPaymentLink($instalment->razorpay_payment_link_id);
                if (in_array($state['status'], ['created', 'partially_paid'], true)) {
                    $this->razorpay->cancelPaymentLink($instalment->razorpay_payment_link_id);
                }
            } catch (Throwable $e) {
                Log::warning('Could not cancel Razorpay payment link while recording a manual payment.', [
                    'instalment_id' => $instalment->id,
                    'payment_link_id' => $instalment->razorpay_payment_link_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $payment = Payment::query()->create([
            'tenant_id' => $instalment->tenant_id,
            'fee_plan_id' => $instalment->fee_plan_id,
            'instalment_id' => $instalment->id,
            'razorpay_order_id' => null,
            'razorpay_payment_id' => null,
            'amount_paise' => $instalment->amount_paise,
            'status' => PaymentStatus::Captured->value,
            'method' => $method,
            'captured_at' => ($paidOn ?? Carbon::today())->startOfDay(),
            'raw' => array_filter([
                'manual' => true,
                'note' => $note,
                'recorded_by' => $actor?->name,
                'reference' => $reference,
                'proof_url' => $proofUrl,
            ], static fn ($v) => $v !== null),
        ]);

        // Settles the instalment, writes the ledger credit + GST receipt,
        // flips enrolment on instalment 1, lifts any fee block, marks the
        // plan Paid once nothing is left — identical to a real capture.
        $this->markPaid->handle($instalment, $payment);

        return $payment;
    }
}
