<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\RecordManualPayment;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Marks an instalment paid from money that never touched Razorpay — cash
 * handed to the office, a bank transfer, a cheque, paid to HR directly.
 * Driven by the CRM's Fee Collections page ("Record payment").
 */
final class RecordManualPaymentCommand extends Command
{
    protected $signature = 'fees:record-manual-payment
        {instalment : Instalment id}
        {method : How it actually arrived — e.g. cash, bank_transfer, cheque}
        {--paid-on= : Date it was paid (Y-m-d), defaults to today}
        {--note= : Optional note kept on record}';

    protected $description = 'Mark an instalment paid from a cash/offline payment, outside Razorpay';

    public function handle(RecordManualPayment $record): int
    {
        $instalment = Instalment::query()->withoutGlobalScopes()->with('feePlan.student')->find((int) $this->argument('instalment'));

        if ($instalment === null) {
            $this->error('Instalment not found.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($instalment->tenant_id);
        if ($tenant === null) {
            $this->error('Instalment has no tenant.');

            return self::FAILURE;
        }

        $paidOn = $this->option('paid-on') !== null
            ? Carbon::parse((string) $this->option('paid-on'))
            : null;

        try {
            $payment = app(TenantContext::class)->run(
                $tenant,
                fn () => $record->handle($instalment, (string) $this->argument('method'), $paidOn, $this->option('note')),
            );
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $student = $instalment->feePlan?->student;

        $this->info(sprintf(
            'Marked instalment %d (₹%s) paid for %s — recorded as %s.',
            $instalment->seq,
            number_format($payment->amount_paise / 100, 2),
            $student?->name ?? 'student',
            $this->argument('method'),
        ));

        return self::SUCCESS;
    }
}
