<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\SetAgreedFee;
use App\Enums\FeePlanType;
use App\Enums\InstalmentStatus;
use App\Models\Tenant;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Validation\ValidationException;

/**
 * "The course is ₹30,000 but this student agreed ₹25,000." The list price stays
 * on the plan, the gap is recorded as a discount, and only the unpaid part is
 * re-cut — money already taken is never rewritten.
 */
beforeEach(function () {
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->tenant = Tenant::factory()->create();
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    $this->plan = withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $student, $batch, FeePlanType::Emi, 3,
    ));
});

it('re-prices the plan and spreads the new balance', function () {
    $result = withinTenant($this->tenant, fn () => app(SetAgreedFee::class)
        ->handle($this->plan, 25_00_000, 5, null, 'Negotiated'));

    $this->plan->refresh()->load('instalments');

    expect($result['agreed_paise'])->toBe(25_00_000)
        ->and($this->plan->discount_paise)->toBe(5_00_000)
        // Course price is preserved — the books keep both numbers.
        ->and($this->plan->total_paise)->toBe(30_00_000)
        ->and($this->plan->netPayablePaise())->toBe(25_00_000)
        ->and($this->plan->instalments)->toHaveCount(5)
        ->and((int) $this->plan->instalments->sum('amount_paise'))->toBe(25_00_000);
});

it('honours what the student already paid', function () {
    $first = $this->plan->instalments->firstWhere('seq', 1);
    $first->update(['status' => InstalmentStatus::Paid->value, 'paid_at' => now()]);
    $paid = (int) $first->amount_paise;

    withinTenant($this->tenant, fn () => app(SetAgreedFee::class)->handle($this->plan->fresh('instalments'), 20_00_000, 2));

    $this->plan->refresh()->load('instalments');
    $unpaid = $this->plan->instalments->where('status', '!=', InstalmentStatus::Paid);

    expect((int) $this->plan->instalments->sum('amount_paise'))->toBe(20_00_000)
        ->and((int) $unpaid->sum('amount_paise'))->toBe(20_00_000 - $paid)
        ->and($this->plan->instalments->where('status', InstalmentStatus::Paid)->count())->toBe(1);
});

it('clears the schedule when they have already paid the agreed amount', function () {
    $this->plan->instalments->each->update(['status' => InstalmentStatus::Paid->value, 'paid_at' => now()]);

    withinTenant($this->tenant, fn () => app(SetAgreedFee::class)->handle($this->plan->fresh('instalments'), 30_00_000, 1));

    expect($this->plan->fresh()->load('instalments')->instalments->where('status', '!=', InstalmentStatus::Paid))->toHaveCount(0);
});

it('refuses an agreed fee below what is already paid', function () {
    $this->plan->instalments->firstWhere('seq', 1)->update(['status' => InstalmentStatus::Paid->value, 'paid_at' => now()]);

    withinTenant($this->tenant, fn () => app(SetAgreedFee::class)->handle($this->plan->fresh('instalments'), 5_00_000, 2));
})->throws(ValidationException::class);

it('refuses an agreed fee above the course price', function () {
    withinTenant($this->tenant, fn () => app(SetAgreedFee::class)->handle($this->plan, 50_00_000, 3));
})->throws(ValidationException::class);
