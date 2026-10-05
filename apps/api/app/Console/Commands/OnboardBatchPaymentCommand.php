<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\OnboardBatchPayment;
use App\Enums\FeePlanType;
use App\Models\Batch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Raises a fee plan for a student who paid before ever choosing one on their
 * own dashboard, and settles its first instalment from that payment. Driven
 * by the CRM's "Onboard batch payments" screen — one student a batch hasn't
 * billed yet, picked from that batch's own Reserved / Payment Pending list.
 */
final class OnboardBatchPaymentCommand extends Command
{
    protected $signature = 'fees:onboard-payment
        {batch : Batch id}
        {student : Student user id}
        {type : single or emi}
        {--emi-count=2 : Instalment count when type=emi}
        {--method=cash : How the first payment arrived}
        {--paid-on= : Date it was paid (Y-m-d), defaults to today}
        {--reference= : Optional transaction id / UTR kept on record}
        {--proof-url= : Optional URL to an uploaded proof image}
        {--amount= : Agreed total in rupees, when it differs from the course price — the gap is booked as a discount, same as Set Amount}';

    protected $description = "Raise a fee plan for a student who already paid, and settle its first instalment";

    public function handle(OnboardBatchPayment $onboard): int
    {
        $batch = Batch::query()->find((int) $this->argument('batch'));
        if ($batch === null) {
            $this->error('Batch not found.');

            return self::FAILURE;
        }

        $student = User::query()->find((int) $this->argument('student'));
        if ($student === null) {
            $this->error('Student not found.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($batch->tenant_id);
        if ($tenant === null) {
            $this->error('Batch has no tenant.');

            return self::FAILURE;
        }

        $type = FeePlanType::tryFrom((string) $this->argument('type'));
        if ($type === null) {
            $this->error("Type must be 'single' or 'emi'.");

            return self::FAILURE;
        }

        $paidOn = $this->option('paid-on') !== null
            ? Carbon::parse((string) $this->option('paid-on'))
            : null;

        $agreedTotalPaise = $this->option('amount') !== null && trim((string) $this->option('amount')) !== ''
            ? (int) round(((float) $this->option('amount')) * 100)
            : null;

        try {
            $plan = app(TenantContext::class)->run(
                $tenant,
                fn () => $onboard->handle(
                    tenant: $tenant,
                    student: $student,
                    batch: $batch,
                    type: $type,
                    emiCount: (int) $this->option('emi-count'),
                    method: (string) $this->option('method'),
                    paidOn: $paidOn,
                    reference: $this->option('reference'),
                    proofUrl: $this->option('proof-url'),
                    agreedTotalPaise: $agreedTotalPaise,
                ),
            );
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $first = $plan->instalments->sortBy('seq')->first();

        $this->info(sprintf(
            '%s: %s plan raised (₹%s total), instalment 1 (₹%s) marked paid.',
            $student->name,
            $type->value,
            number_format($plan->total_paise / 100, 2),
            number_format(($first?->amount_paise ?? 0) / 100, 2),
        ));

        return self::SUCCESS;
    }
}
