<?php

declare(strict_types=1);

use App\Actions\Store\PublishSelfPacedCourse;
use App\Actions\Store\ReconcilePurchase;
use App\Actions\Store\UpgradeToLive;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\EnrolmentType;
use App\Events\ModuleCompleted;
use App\Listeners\GrantModuleReward;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('s3');
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->svc = app(EntitlementService::class);
    $course = Course::factory()->for($this->tenant)->create();
    $this->batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value, 'status' => 'completed']);
});

it('publishes a completed batch as self-paced at the configured %', function () {
    $product = withinTenant($this->tenant, fn () => app(PublishSelfPacedCourse::class)->handle($this->batch));

    // 50% of ₹30,000.
    expect($product->price_paise)->toBe(1_500_000)
        ->and($product->kind->value)->toBe('self_paced')
        ->and($product->source_batch_id)->toBe($this->batch->id);
});

it('refuses to publish a non-completed batch', function () {
    $this->batch->update(['status' => 'active']);

    expect(fn () => withinTenant($this->tenant, fn () => app(PublishSelfPacedCourse::class)->handle($this->batch)))
        ->toThrow(ValidationException::class);
});

it('upgrade-to-live is difference-priced and flips the enrolment', function () {
    Product::factory()->for($this->tenant)->create(['sku' => "self-paced-{$this->batch->id}", 'kind' => 'self_paced', 'price_paise' => 1_500_000, 'source_batch_id' => $this->batch->id]);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $this->batch->id, 'user_id' => $this->student->id, 'status' => BatchMemberStatus::Enrolled->value, 'enrolment_type' => EnrolmentType::SelfPaced->value]);

    $purchase = withinTenant($this->tenant, fn () => app(UpgradeToLive::class)->handle($this->student, $this->batch));
    expect($purchase->amount_paise)->toBe(1_500_000); // 3,000,000 − 1,500,000

    app(ReconcilePurchase::class)->handle(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
        'id' => 'pay_UP', 'order_id' => $purchase->razorpay_order_id, 'amount' => 1_500_000,
    ]]]]);

    expect(BatchMember::withoutGlobalScopes()->where('batch_id', $this->batch->id)->where('user_id', $this->student->id)->first()->enrolment_type)
        ->toBe(EnrolmentType::Live);
});

it('module completion grants a capped voice-mock credit', function () {
    withinTenant($this->tenant, function () {
        for ($i = 0; $i < 7; $i++) {
            app(GrantModuleReward::class)->handle(new ModuleCompleted($this->student, new Module));
        }
        // Capped at the included live quota (5).
        expect($this->svc->balance($this->student, 'voice_mock'))->toBe(5);
    });
});
