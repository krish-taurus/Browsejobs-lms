<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\RestructureFeePlan;
use App\Enums\FeePlanType;
use App\Enums\InstalmentStatus;
use App\Models\Tenant;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * "Can I pay in more, smaller EMIs?" — re-cutting the unpaid balance. The money
 * must never move: what is already paid stays paid, and what is still owed stays
 * exactly the same number.
 */
beforeEach(function () {
    $this->razorpay = new FakeRazorpayClient;
    app()->instance(RazorpayClient::class, $this->razorpay);
    $this->tenant = Tenant::factory()->create();

    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    $this->plan = withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $student, $batch, FeePlanType::Emi, 3,
    ));
});

it('splits the outstanding balance into more instalments without changing the total', function () {
    $owedBefore = (int) $this->plan->instalments->sum('amount_paise');

    $result = withinTenant($this->tenant, fn () => app(RestructureFeePlan::class)
        ->handle($this->plan, 6, Carbon::parse('2026-09-01')));

    $this->plan->refresh()->load('instalments');

    expect($this->plan->instalments)->toHaveCount(6)
        ->and((int) $this->plan->instalments->sum('amount_paise'))->toBe($owedBefore)
        ->and($result['created'])->toBe(6)
        ->and($result['removed'])->toBe(3)
        ->and($this->plan->instalments->sortBy('seq')->first()->due_on->toDateString())->toBe('2026-09-01')
        ->and($this->plan->instalments->sortBy('seq')->last()->due_on->toDateString())->toBe('2027-02-01');
});

it('never touches an instalment the student already paid', function () {
    $first = $this->plan->instalments->firstWhere('seq', 1);
    $first->update(['status' => InstalmentStatus::Paid->value, 'paid_at' => now()]);

    $paidAmount = (int) $first->amount_paise;
    $stillOwed = (int) $this->plan->instalments->where('seq', '!=', 1)->sum('amount_paise');

    withinTenant($this->tenant, fn () => app(RestructureFeePlan::class)->handle($this->plan, 4));

    $this->plan->refresh()->load('instalments');
    $paid = $this->plan->instalments->where('status', InstalmentStatus::Paid);
    $unpaid = $this->plan->instalments->where('status', '!=', InstalmentStatus::Paid);

    expect($paid)->toHaveCount(1)
        ->and((int) $paid->sum('amount_paise'))->toBe($paidAmount)
        ->and($unpaid)->toHaveCount(4)
        ->and((int) $unpaid->sum('amount_paise'))->toBe($stillOwed)
        // New rows continue the numbering rather than colliding with the paid one.
        ->and($unpaid->min('seq'))->toBe(2);
});

it('cancels a live payment link so the student cannot pay a stale amount', function () {
    $this->plan->instalments->firstWhere('seq', 1)
        ->update(['razorpay_payment_link_id' => 'plink_OLD1']);

    $result = withinTenant($this->tenant, fn () => app(RestructureFeePlan::class)->handle($this->plan, 5));

    expect($this->razorpay->cancelledLinks)->toContain('plink_OLD1')
        ->and($result['links_cancelled'])->toContain('plink_OLD1')
        ->and($result['links_failed'])->toBe([]);
});

it('leaves the plan untouched when the split is out of range', function () {
    withinTenant($this->tenant, fn () => app(RestructureFeePlan::class)->handle($this->plan, 99));
})->throws(ValidationException::class);

it('refuses when there is nothing left to pay', function () {
    $this->plan->instalments->each->update(['status' => InstalmentStatus::Paid->value, 'paid_at' => now()]);

    withinTenant($this->tenant, fn () => app(RestructureFeePlan::class)->handle($this->plan->refresh(), 3));
})->throws(ValidationException::class);

it('rounds so the parts always sum back to the exact balance', function () {
    // ₹30,000 over 7 does not divide evenly — the remainder must not vanish.
    $owedBefore = (int) $this->plan->instalments->sum('amount_paise');

    withinTenant($this->tenant, fn () => app(RestructureFeePlan::class)->handle($this->plan, 7));

    $this->plan->refresh()->load('instalments');

    expect((int) $this->plan->instalments->sum('amount_paise'))->toBe($owedBefore)
        ->and($this->plan->instalments)->toHaveCount(7);
});
