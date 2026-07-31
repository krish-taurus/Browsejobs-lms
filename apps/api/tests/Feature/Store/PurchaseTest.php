<?php

declare(strict_types=1);

use App\Actions\LiveClasses\JoinLiveSession;
use App\Actions\Store\ReconcilePurchase;
use App\Actions\Store\StartPurchase;
use App\Enums\BatchType;
use App\Enums\EnrolmentType;
use App\Enums\ProductKind;
use App\Events\ProductPurchased;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // Balances are explicit test inputs — disable the first-touch free allowance.
    config(['monetization.voice_mock.free_grants' => 0, 'monetization.voice_mock.included_live' => 5, 'monetization.voice_mock.included_self_paced' => 2]);
    Storage::fake('s3');
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->pack = Product::factory()->for($this->tenant)->create(['sku' => 'voice-3pack', 'feature' => 'voice_mock', 'kind' => ProductKind::Pack->value, 'price_paise' => 59_900, 'grant_amount' => 3]);
    $this->svc = app(EntitlementService::class);
});

function capturedFor(string $orderId, string $paymentId): array
{
    return ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
        'id' => $paymentId, 'order_id' => $orderId, 'amount' => 59_900, 'method' => 'upi',
    ]]]];
}

it('starts a purchase: pending row + razorpay order', function () {
    $purchase = withinTenant($this->tenant, fn () => app(StartPurchase::class)->handle($this->student, $this->pack));

    expect($purchase->status->value)->toBe('pending')
        ->and($purchase->razorpay_order_id)->toStartWith('order_TEST');
});

it('settles a captured purchase: grants credits, GST receipt, event', function () {
    Event::fake([ProductPurchased::class]);

    $purchase = withinTenant($this->tenant, fn () => app(StartPurchase::class)->handle($this->student, $this->pack));
    app(ReconcilePurchase::class)->handle(capturedFor($purchase->razorpay_order_id, 'pay_A'));

    $fresh = ProductPurchase::withoutGlobalScopes()->find($purchase->id);
    expect($fresh->status->value)->toBe('captured')
        ->and($fresh->taxable_paise + $fresh->cgst_paise + $fresh->sgst_paise)->toBe($fresh->total_paise)
        ->and($fresh->receipt_number)->toStartWith('BJ-P/')
        ->and($this->svc->balance($this->student, 'voice_mock'))->toBe(3)
        ->and(Storage::disk('s3')->exists($fresh->receipt_path))->toBeTrue()
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'store.purchase_captured')->count())->toBe(1);

    Event::assertDispatched(ProductPurchased::class);
});

it('is idempotent on a duplicate capture', function () {
    $purchase = withinTenant($this->tenant, fn () => app(StartPurchase::class)->handle($this->student, $this->pack));
    app(ReconcilePurchase::class)->handle(capturedFor($purchase->razorpay_order_id, 'pay_B'));
    app(ReconcilePurchase::class)->handle(capturedFor($purchase->razorpay_order_id, 'pay_B'));

    expect($this->svc->balance($this->student, 'voice_mock'))->toBe(3)
        ->and(ProductPurchase::withoutGlobalScopes()->where('status', 'captured')->count())->toBe(1);
});

it('self-paced purchase enrols self_paced and blocks live join', function () {
    $course = Course::factory()->for($this->tenant)->create();
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value, 'status' => 'completed']);
    $product = Product::factory()->for($this->tenant)->create(['sku' => "self-paced-{$batch->id}", 'feature' => 'self_paced', 'kind' => ProductKind::SelfPaced->value, 'price_paise' => 1_500_000, 'source_batch_id' => $batch->id]);

    $purchase = withinTenant($this->tenant, fn () => app(StartPurchase::class)->handle($this->student, $product));
    app(ReconcilePurchase::class)->handle(capturedFor($purchase->razorpay_order_id, 'pay_SP'));

    $member = BatchMember::withoutGlobalScopes()->where('batch_id', $batch->id)->where('user_id', $this->student->id)->first();
    expect($member)->not->toBeNull()
        ->and($member->enrolment_type)->toBe(EnrolmentType::SelfPaced)
        ->and($this->svc->hasEntitlement($this->student, "self_paced:{$batch->id}"))->toBeTrue()
        ->and($this->svc->balance($this->student, 'voice_mock'))->toBe(2); // self-paced included

    $session = LiveSession::query()->create([
        'tenant_id' => $this->tenant->id, 'batch_id' => $batch->id, 'title' => 'Class',
        'scheduled_start' => now(), 'status' => 'scheduled', 'zoom_join_url' => 'https://zoom.test/j/1',
    ]);
    expect(fn () => withinTenant($this->tenant, fn () => app(JoinLiveSession::class)->handle($session, $this->student)))
        ->toThrow(ValidationException::class);
});
