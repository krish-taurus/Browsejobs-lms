<?php

declare(strict_types=1);

use App\Actions\Payments\BulkSendPaymentLinks;
use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\CreateInstalmentOrder;
use App\Actions\Payments\SendPaymentLink;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\FeePlanType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;

beforeEach(function () {
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->tenant = Tenant::factory()->create();
});

it('creates a personalized payment link for an instalment', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    $instalment = withinTenant($this->tenant, function () use ($batch, $student) {
        $plan = app(CreateFeePlan::class)->handle($this->tenant, $student, $batch, FeePlanType::Single, 1);
        $inst = $plan->instalments()->firstOrFail();

        return app(SendPaymentLink::class)->handle($inst);
    });

    expect($instalment->payment_link_url)->toContain('rzp.test')
        ->and($instalment->razorpay_payment_link_id)->toStartWith('plink_TEST');
});

it('creates a Razorpay order + pending payment for an instalment', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    $payment = withinTenant($this->tenant, function () use ($batch, $student) {
        $plan = app(CreateFeePlan::class)->handle($this->tenant, $student, $batch, FeePlanType::Single, 1);

        return app(CreateInstalmentOrder::class)->handle($plan->instalments()->firstOrFail());
    });

    expect($payment->razorpay_order_id)->toStartWith('order_TEST')
        ->and($payment->status->value)->toBe('created');
});

it('bulk-sends links to every active plan in a batch', function () {
    $course = Course::factory()->for($this->tenant)->create();
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value]);

    $count = withinTenant($this->tenant, function () use ($batch) {
        foreach (range(1, 2) as $i) {
            $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => "+9190000000{$i}"]);
            BatchMember::factory()->for($this->tenant)->create([
                'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::Reserved->value,
            ]);
            app(CreateFeePlan::class)->handle($this->tenant, $student, $batch, FeePlanType::Emi, 3);
        }

        return app(BulkSendPaymentLinks::class)->handle($batch);
    });

    expect($count)->toBe(2)
        ->and(Instalment::withoutGlobalScopes()->where('seq', 1)->whereNotNull('payment_link_url')->count())->toBe(2);
});
