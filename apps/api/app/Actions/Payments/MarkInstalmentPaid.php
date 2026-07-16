<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Enums\LedgerDirection;
use App\Events\PaymentCaptured;
use App\Jobs\FlipEnrolment;
use App\Jobs\RenderReceipt;
use App\Models\Instalment;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\Audit\AuditLogger;
use App\Support\Money\GstCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Settles a captured instalment (PRD §6.8): marks it paid, writes the ledger
 * credit, raises the GST receipt, flips enrolment on instalment 1, and marks the
 * plan paid when complete. Idempotent — a re-run on an already-paid instalment
 * is a no-op (webhook may deliver more than once). Runs inside the tenant
 * context established by the caller.
 */
final readonly class MarkInstalmentPaid
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Instalment $instalment, Payment $payment): void
    {
        if ($instalment->status === InstalmentStatus::Paid) {
            return;
        }

        $feePlan = $instalment->feePlan;

        $receipt = DB::transaction(function () use ($instalment, $payment, $feePlan): Receipt {
            $instalment->status = InstalmentStatus::Paid;
            $instalment->paid_at = now();
            $instalment->save();

            LedgerEntry::query()->create([
                'tenant_id' => $instalment->tenant_id,
                'user_id' => $feePlan->user_id,
                'fee_plan_id' => $feePlan->id,
                'payment_id' => $payment->id,
                'direction' => LedgerDirection::Credit->value,
                'amount_paise' => $payment->amount_paise,
                'description' => "Instalment {$instalment->seq} paid",
                'occurred_at' => now(),
            ]);

            $gst = GstCalculator::inclusive($payment->amount_paise, $feePlan->gst_rate_bps);
            $receipt = Receipt::query()->create([
                'tenant_id' => $instalment->tenant_id,
                'payment_id' => $payment->id,
                'fee_plan_id' => $feePlan->id,
                'number' => $this->nextReceiptNumber($instalment->tenant_id),
                'taxable_paise' => $gst->taxablePaise,
                'cgst_paise' => $gst->cgstPaise,
                'sgst_paise' => $gst->sgstPaise,
                'total_paise' => $gst->totalPaise,
                'status' => Receipt::STATUS_PENDING,
            ]);

            $allPaid = ! $feePlan->instalments()
                ->where('status', '!=', InstalmentStatus::Paid->value)
                ->exists();
            if ($allPaid) {
                $feePlan->status = FeePlanStatus::Paid;
                $feePlan->save();
            }

            $this->audit->log(
                action: 'fees.instalment_paid',
                target: $instalment,
                metadata: ['seq' => $instalment->seq, 'amount_paise' => $payment->amount_paise, 'receipt' => $receipt->number],
            );

            return $receipt;
        });

        PaymentCaptured::dispatch($payment, $instalment);

        if ($instalment->seq === 1) {
            FlipEnrolment::dispatch($feePlan->id);
        }

        RenderReceipt::dispatch($receipt->id);
    }

    private function nextReceiptNumber(int $tenantId): string
    {
        $seq = Receipt::query()->where('tenant_id', $tenantId)->count() + 1;

        return 'BJ/'.now()->format('Y').'/'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
