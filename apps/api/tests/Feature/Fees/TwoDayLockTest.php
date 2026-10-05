<?php

declare(strict_types=1);

use App\Actions\Fees\ExtendFeeAccess;
use App\Actions\Fees\RunDunningLadder;
use App\Actions\Payments\CreateFeePlan;
use App\Enums\FeePlanType;
use App\Models\AccessBlock;
use App\Models\Tenant;

/**
 * The founder's rule: miss an EMI, and two days later classes and recordings
 * lock by themselves — unless someone has bought the student more time.
 */
beforeEach(function () {
    config(['fees.ladder.grace_days' => 2]);

    $this->tenant = Tenant::factory()->create();
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);
    $this->student = $student;

    $this->plan = withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $student, $batch, FeePlanType::Emi, 3,
    ));

    // First instalment fell due three days ago and was never paid.
    $this->plan->instalments->firstWhere('seq', 1)
        ->forceFill(['due_on' => now()->subDays(3)->toDateString()])->save();
});

it('locks classes and recordings once the instalment is more than 2 days overdue', function () {
    withinTenant($this->tenant, fn () => app(RunDunningLadder::class)->handle());

    $block = AccessBlock::withoutGlobalScopes()->where('user_id', $this->student->id)->whereNull('lifted_at')->first();

    expect($block)->not->toBeNull()
        ->and($block->reason)->toContain('Overdue');
});

it('does not lock a student whose access has been extended', function () {
    withinTenant($this->tenant, fn () => app(ExtendFeeAccess::class)->handle($this->plan, 5, 'Salary on the 5th'));

    withinTenant($this->tenant, fn () => app(RunDunningLadder::class)->handle());

    expect(AccessBlock::withoutGlobalScopes()->whereNull('lifted_at')->count())->toBe(0);
});
