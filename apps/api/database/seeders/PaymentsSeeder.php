<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Payments\CreateFeePlan;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\FeePlanType;
use App\Enums\InstalmentStatus;
use App\Enums\LedgerDirection;
use App\Enums\PaymentStatus;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\FeePlan;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money\GstCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo fee plan (EMI 3×) for a Reserved student in a paid batch, with
 * instalment 1 already captured (ledger credit + receipt + enrolment flipped),
 * so /admin/payments is demo-able. Idempotent. Runs the settle inline (no queued
 * jobs) so seeding needs no queue/object-storage.
 */
class PaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, fn () => $this->seedTenant($tenant));
    }

    private function seedTenant(Tenant $tenant): void
    {
        if (FeePlan::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $course = Course::query()->where('tenant_id', $tenant->id)->first()
            ?? Course::query()->create([
                'tenant_id' => $tenant->id, 'code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'data-engineering', 'status' => 'live',
            ]);

        $batch = Batch::query()->create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'number' => 'DE-202607-PAY',
            'type' => BatchType::Paid->value,
            'status' => 'active',
        ]);

        $student = User::query()->firstOrCreate(
            ['email' => 'priya.sharma@example.com'],
            ['tenant_id' => $tenant->id, 'name' => 'Priya Sharma', 'user_type' => 'student', 'phone' => '+919876510000'],
        );

        $member = BatchMember::query()->create([
            'tenant_id' => $tenant->id,
            'batch_id' => $batch->id,
            'user_id' => $student->id,
            'status' => BatchMemberStatus::Reserved->value,
        ]);

        $plan = app(CreateFeePlan::class)->handle($tenant, $student, $batch, FeePlanType::Emi, 3);

        // Settle instalment 1 inline (mirrors MarkInstalmentPaid without queues).
        $first = $plan->instalments()->where('seq', 1)->firstOrFail();
        $payment = Payment::query()->create([
            'tenant_id' => $tenant->id,
            'fee_plan_id' => $plan->id,
            'instalment_id' => $first->id,
            'razorpay_order_id' => 'order_DEMO0001',
            'razorpay_payment_id' => 'pay_DEMO0001',
            'amount_paise' => $first->amount_paise,
            'status' => PaymentStatus::Captured->value,
            'captured_at' => now(),
        ]);
        $first->update(['status' => InstalmentStatus::Paid->value, 'paid_at' => now()]);

        LedgerEntry::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $student->id, 'fee_plan_id' => $plan->id,
            'payment_id' => $payment->id, 'direction' => LedgerDirection::Credit->value,
            'amount_paise' => $payment->amount_paise, 'description' => 'Instalment 1 paid', 'occurred_at' => now(),
        ]);

        $gst = GstCalculator::inclusive($payment->amount_paise, $plan->gst_rate_bps);
        Receipt::query()->create([
            'tenant_id' => $tenant->id, 'payment_id' => $payment->id, 'fee_plan_id' => $plan->id,
            'number' => 'BJ/'.now()->format('Y').'/00001',
            'taxable_paise' => $gst->taxablePaise, 'cgst_paise' => $gst->cgstPaise,
            'sgst_paise' => $gst->sgstPaise, 'total_paise' => $gst->totalPaise,
            'status' => Receipt::STATUS_PENDING,
        ]);

        $member->update(['status' => BatchMemberStatus::Enrolled->value, 'enrolled_at' => now()]);
    }
}
