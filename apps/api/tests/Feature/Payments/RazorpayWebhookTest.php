<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\CreateInstalmentOrder;
use App\Enums\BatchMemberStatus;
use App\Enums\FeePlanType;
use App\Enums\InstalmentStatus;
use App\Enums\LedgerDirection;
use App\Enums\PaymentStatus;
use App\Events\PaymentCaptured;
use App\Models\BatchMember;
use App\Models\Instalment;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.razorpay.webhook_secret' => 'rzp-secret']);
    Storage::fake('s3');
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);

    $this->tenant = Tenant::factory()->create();
    $setup = reservedMemberIn($this->tenant, '+91 90000 12345', 'stu@example.com');
    $this->batch = $setup['batch'];
    $this->student = $setup['student'];
    $this->member = $setup['member'];

    [$this->plan, $this->inst1] = withinTenant($this->tenant, function () {
        LeadStage::query()->create(['name' => 'Reserved', 'slug' => 'reserved', 'position' => 5]);
        $enrolled = LeadStage::query()->create(['name' => 'Enrolled', 'slug' => 'enrolled', 'position' => 6]);

        Lead::factory()->for($this->tenant)->create([
            'name' => 'Stu', 'phone' => '+91 90000 12345',
            'phone_normalized' => PhoneNormalizer::normalize('+91 90000 12345'),
            'email' => 'stu@example.com',
            'lead_stage_id' => LeadStage::query()->where('slug', 'reserved')->value('id'),
        ]);
        $this->enrolledStage = $enrolled;

        $plan = app(CreateFeePlan::class)->handle($this->tenant, $this->student, $this->batch, FeePlanType::Emi, 3);
        $inst1 = $plan->instalments()->where('seq', 1)->firstOrFail();
        app(CreateInstalmentOrder::class)->handle($inst1);

        return [$plan, $inst1->refresh()];
    });
});

function postRazorpay(array $payload)
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'rzp-secret');

    return test()->call('POST', '/api/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body);
}

function capturedPayload(string $orderId, string $paymentId, int $amount): array
{
    return ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
        'id' => $paymentId, 'order_id' => $orderId, 'amount' => $amount, 'method' => 'upi', 'status' => 'captured',
    ]]]];
}

it('rejects an unsigned webhook', function () {
    $this->postJson('/api/webhooks/razorpay', ['event' => 'payment.captured'])->assertForbidden();
});

it('rejects a wrong signature', function () {
    test()->call('POST', '/api/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => 'deadbeef',
    ], json_encode(['event' => 'payment.captured']))->assertForbidden();
});

it('captures instalment 1: settles the ledger, receipt, and flips enrolment + CRM stage', function () {
    postRazorpay(capturedPayload($this->inst1->razorpay_order_id, 'pay_ONE', $this->inst1->amount_paise))
        ->assertOk();

    // Instalment + payment
    expect(Instalment::withoutGlobalScopes()->find($this->inst1->id)->status)->toBe(InstalmentStatus::Paid)
        ->and(Payment::withoutGlobalScopes()->where('razorpay_payment_id', 'pay_ONE')->first()->status)->toBe(PaymentStatus::Captured);

    // Ledger credit + receipt rendered
    expect(LedgerEntry::withoutGlobalScopes()->where('fee_plan_id', $this->plan->id)->where('direction', LedgerDirection::Credit->value)->count())->toBe(1);
    $receipt = Receipt::withoutGlobalScopes()->where('fee_plan_id', $this->plan->id)->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe(Receipt::STATUS_RENDERED)
        ->and($receipt->taxable_paise + $receipt->cgst_paise + $receipt->sgst_paise)->toBe($receipt->total_paise);
    Storage::disk('s3')->assertExists($receipt->storage_path);

    // Enrolment flip
    expect(BatchMember::withoutGlobalScopes()->find($this->member->id)->status)->toBe(BatchMemberStatus::Enrolled)
        ->and(BatchMember::withoutGlobalScopes()->find($this->member->id)->enrolled_at)->not->toBeNull();

    // CRM lead stage flip
    $lead = Lead::withoutGlobalScopes()->where('email', 'stu@example.com')->first();
    expect($lead->lead_stage_id)->toBe($this->enrolledStage->id);
});

it('fires the PaymentCaptured event', function () {
    Event::fake([PaymentCaptured::class]);

    postRazorpay(capturedPayload($this->inst1->razorpay_order_id, 'pay_EVT', $this->inst1->amount_paise))->assertOk();

    Event::assertDispatched(PaymentCaptured::class);
});

it('is idempotent — a duplicate capture is processed once', function () {
    $payload = capturedPayload($this->inst1->razorpay_order_id, 'pay_DUP', $this->inst1->amount_paise);
    postRazorpay($payload)->assertOk();
    postRazorpay($payload)->assertOk();

    expect(LedgerEntry::withoutGlobalScopes()->where('fee_plan_id', $this->plan->id)->where('direction', LedgerDirection::Credit->value)->count())->toBe(1)
        ->and(Receipt::withoutGlobalScopes()->where('fee_plan_id', $this->plan->id)->count())->toBe(1)
        ->and(Payment::withoutGlobalScopes()->where('razorpay_payment_id', 'pay_DUP')->count())->toBe(1);
});

it('marks the instalment failed on payment.failed', function () {
    postRazorpay(['event' => 'payment.failed', 'payload' => ['payment' => ['entity' => [
        'id' => 'pay_FAIL', 'order_id' => $this->inst1->razorpay_order_id, 'amount' => $this->inst1->amount_paise, 'status' => 'failed',
    ]]]])->assertOk();

    expect(Instalment::withoutGlobalScopes()->find($this->inst1->id)->status)->toBe(InstalmentStatus::Failed)
        ->and(BatchMember::withoutGlobalScopes()->find($this->member->id)->status)->toBe(BatchMemberStatus::Reserved);
});
