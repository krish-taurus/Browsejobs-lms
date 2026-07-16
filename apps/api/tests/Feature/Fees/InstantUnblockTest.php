<?php

declare(strict_types=1);

use App\Actions\Fees\LiftAccessBlocks;
use App\Actions\Payments\MarkInstalmentPaid;
use App\Enums\BatchMemberStatus;
use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Enums\PaymentStatus;
use App\Jobs\LiftFeeBlocks;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fees\DuesFeeGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    $this->tenant = Tenant::factory()->create();
    $this->batch = Batch::factory()->for($this->tenant)->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($this->tenant)->create([
        'batch_id' => $this->batch->id, 'user_id' => $this->student->id, 'status' => BatchMemberStatus::Enrolled->value,
    ]);
    $this->plan = FeePlan::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'batch_id' => $this->batch->id, 'status' => FeePlanStatus::Active->value,
    ]);
    AccessBlock::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'batch_id' => $this->batch->id, 'fee_plan_id' => $this->plan->id,
    ]);
});

function overdueInstalment(Tenant $tenant, FeePlan $plan, int $seq, int $daysAgo): Instalment
{
    return Instalment::factory()->for($tenant)->create([
        'fee_plan_id' => $plan->id, 'seq' => $seq, 'amount_paise' => 1_000_000,
        'due_on' => Carbon::today()->subDays($daysAgo)->toDateString(), 'status' => InstalmentStatus::Pending->value,
    ]);
}

it('lifts the block when the last overdue instalment is paid', function () {
    $inst = overdueInstalment($this->tenant, $this->plan, 1, 10);
    $inst->update(['status' => InstalmentStatus::Paid->value]);

    (new LiftFeeBlocks($this->plan->id))->handle(app(LiftAccessBlocks::class));

    $block = AccessBlock::withoutGlobalScopes()->where('user_id', $this->student->id)->first();
    expect($block->lifted_at)->not->toBeNull()
        ->and(app(DuesFeeGate::class)->allowsLiveAccess($this->student, $this->batch))->toBeTrue();
});

it('keeps the block when another overdue instalment remains', function () {
    overdueInstalment($this->tenant, $this->plan, 1, 10); // still pending + overdue

    (new LiftFeeBlocks($this->plan->id))->handle(app(LiftAccessBlocks::class));

    expect(app(DuesFeeGate::class)->allowsLiveAccess($this->student, $this->batch))->toBeFalse();
});

it('unblocks instantly through MarkInstalmentPaid on capture', function () {
    $inst = overdueInstalment($this->tenant, $this->plan, 1, 10);
    $payment = Payment::factory()->for($this->tenant)->create([
        'fee_plan_id' => $this->plan->id, 'instalment_id' => $inst->id,
        'razorpay_payment_id' => 'pay_UNBLOCK', 'status' => PaymentStatus::Captured->value, 'captured_at' => now(),
    ]);

    withinTenant($this->tenant, fn () => app(MarkInstalmentPaid::class)->handle($inst->fresh(), $payment));

    $block = AccessBlock::withoutGlobalScopes()->where('user_id', $this->student->id)->first();
    expect($block->lifted_at)->not->toBeNull()
        ->and(app(DuesFeeGate::class)->allowsLiveAccess($this->student, $this->batch))->toBeTrue();
});
