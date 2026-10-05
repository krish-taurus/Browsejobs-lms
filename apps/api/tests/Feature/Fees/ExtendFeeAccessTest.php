<?php

declare(strict_types=1);

use App\Actions\Fees\ExtendFeeAccess;
use App\Actions\Payments\CreateFeePlan;
use App\Enums\AccessBlockLevel;
use App\Enums\FeePlanType;
use App\Models\AccessBlock;
use App\Models\Tenant;
use App\Support\Fees\DuesFeeGate;
use Illuminate\Validation\ValidationException;

/**
 * The lock is automatic; the reprieve is deliberate. These cover the promise
 * made to the founder: a blocked student regains access the moment an extension
 * is granted, and loses it again by itself when the extension runs out.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);
    $this->batch = $batch;
    $this->student = $student;

    $this->plan = withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $student, $batch, FeePlanType::Emi, 3,
    ));
});

function blockStudent(Tenant $tenant, $plan, $student): void
{
    AccessBlock::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'batch_id' => $plan->batch_id,
        'fee_plan_id' => $plan->id,
        'level' => AccessBlockLevel::Soft->value,
        'reason' => 'Overdue instalment 1',
        'blocked_at' => now(),
    ]);
}

it('locks a student out once a block is in force', function () {
    withinTenant($this->tenant, fn () => blockStudent($this->tenant, $this->plan, $this->student));

    expect(app(DuesFeeGate::class)->allowsLiveAccess($this->student, $this->batch))->toBeFalse();
});

it('restores access immediately when the team grants an extension', function () {
    withinTenant($this->tenant, fn () => blockStudent($this->tenant, $this->plan, $this->student));

    withinTenant($this->tenant, fn () => app(ExtendFeeAccess::class)->handle($this->plan, 5, 'Salary on the 5th'));

    expect(app(DuesFeeGate::class)->allowsLiveAccess($this->student, $this->batch))->toBeTrue()
        ->and($this->plan->fresh()->access_extended_until->toDateString())->toBe(now()->addDays(5)->toDateString())
        // The block is lifted on record, not just ignored.
        ->and(AccessBlock::withoutGlobalScopes()->whereNull('lifted_at')->count())->toBe(0);
});

it('locks the student out again the day after the extension expires', function () {
    withinTenant($this->tenant, fn () => app(ExtendFeeAccess::class)->handle($this->plan, 5));

    $this->travel(6)->days();

    withinTenant($this->tenant, fn () => blockStudent($this->tenant, $this->plan->fresh(), $this->student));

    expect(app(DuesFeeGate::class)->allowsLiveAccess($this->student, $this->batch))->toBeFalse();
});

it('adds a second extension onto the first rather than overwriting it', function () {
    withinTenant($this->tenant, function () {
        app(ExtendFeeAccess::class)->handle($this->plan, 5);
        app(ExtendFeeAccess::class)->handle($this->plan->fresh(), 5);
    });

    expect($this->plan->fresh()->access_extended_until->toDateString())->toBe(now()->addDays(10)->toDateString());
});

it('refuses an extension beyond the sane limit', function () {
    withinTenant($this->tenant, fn () => app(ExtendFeeAccess::class)->handle($this->plan, 90));
})->throws(ValidationException::class);
