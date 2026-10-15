<?php

declare(strict_types=1);

use App\Actions\Fees\LiftAccessBlocks;
use App\Actions\Fees\RunDunningLadder;
use App\Enums\AccessBlockLevel;
use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Jobs\LiftFeeBlocks;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\Course;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->course = Course::factory()->for($this->tenant)->create();
});

/**
 * A student who sat in an old batch (a plan nobody paid, instalment failed),
 * moved to a newer batch of the same course and paid there, and is blocked for
 * the old plan's overdue instalment. This is exactly what happened to a real
 * student on 2 Oct.
 *
 * @return array{student: User, oldPlan: FeePlan, newPlan: FeePlan}
 */
function movedAndPaid(Tenant $tenant, Course $course): array
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $oldBatch = Batch::factory()->for($tenant)->create(['course_id' => $course->id]);
    $newBatch = Batch::factory()->for($tenant)->create(['course_id' => $course->id]);

    $oldPlan = FeePlan::factory()->for($tenant)->create(['user_id' => $student->id, 'batch_id' => $oldBatch->id, 'status' => FeePlanStatus::Active->value]);
    Instalment::factory()->for($tenant)->create([
        'fee_plan_id' => $oldPlan->id, 'seq' => 1, 'amount_paise' => 3_000_000,
        'due_on' => Carbon::today()->subDays(20)->toDateString(), 'status' => InstalmentStatus::Failed->value,
    ]);

    $newPlan = FeePlan::factory()->for($tenant)->create(['user_id' => $student->id, 'batch_id' => $newBatch->id, 'status' => FeePlanStatus::Paid->value]);
    Instalment::factory()->for($tenant)->create([
        'fee_plan_id' => $newPlan->id, 'seq' => 1, 'amount_paise' => 500_000,
        'due_on' => Carbon::today()->subDays(20)->toDateString(), 'status' => InstalmentStatus::Paid->value, 'paid_at' => now()->subDays(20),
    ]);

    AccessBlock::factory()->for($tenant)->create([
        'user_id' => $student->id, 'batch_id' => $oldBatch->id, 'fee_plan_id' => $oldPlan->id,
        'level' => AccessBlockLevel::Hard->value, 'reason' => 'Overdue instalment 1',
    ]);

    return compact('student', 'oldPlan', 'newPlan');
}

function activeBlocks(User $student): int
{
    return AccessBlock::withoutGlobalScopes()->where('user_id', $student->id)->whereNull('lifted_at')->count();
}

it('unblocks someone who moved batch and paid, when the payment is recorded', function (): void {
    ['student' => $student, 'newPlan' => $newPlan] = movedAndPaid($this->tenant, $this->course);
    expect(activeBlocks($student))->toBe(1);

    (new LiftFeeBlocks($newPlan->id))->handle(app(LiftAccessBlocks::class));

    expect(activeBlocks($student))->toBe(0);
});

it('lifts it at midnight too, when the instant unblock never ran', function (): void {
    ['student' => $student] = movedAndPaid($this->tenant, $this->course);

    Artisan::call('fees:reconcile-blocks');

    $block = AccessBlock::withoutGlobalScopes()->where('user_id', $student->id)->sole();
    expect(Artisan::output())->toContain('1 unblocked')
        ->and($block->lifted_at)->not->toBeNull()
        ->and($block->lifted_reason)->toBe('Reconciled: nothing overdue');

    // Running it again changes nothing.
    Artisan::call('fees:reconcile-blocks');
    expect(Artisan::output())->toContain('0 blocked student(s) checked');
});

it('does not block them again the next morning', function (): void {
    ['student' => $student] = movedAndPaid($this->tenant, $this->course);
    Artisan::call('fees:reconcile-blocks');

    $summary = app(RunDunningLadder::class)->handle();

    expect($summary['hard_blocks'])->toBe(0)->and($summary['soft_blocks'])->toBe(0)
        ->and(activeBlocks($student))->toBe(0);
});

it('keeps blocking someone who really owes', function (): void {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $this->course->id]);
    $plan = FeePlan::factory()->for($this->tenant)->create(['user_id' => $student->id, 'batch_id' => $batch->id, 'status' => FeePlanStatus::Active->value]);
    Instalment::factory()->for($this->tenant)->create([
        'fee_plan_id' => $plan->id, 'seq' => 1, 'amount_paise' => 1_000_000,
        'due_on' => Carbon::today()->subDays(12)->toDateString(), 'status' => InstalmentStatus::Pending->value,
    ]);
    AccessBlock::factory()->for($this->tenant)->create(['user_id' => $student->id, 'batch_id' => $batch->id, 'fee_plan_id' => $plan->id]);

    Artisan::call('fees:reconcile-blocks');

    expect(Artisan::output())->toContain('1 still owe')->and(activeBlocks($student))->toBe(1);
});

it('does not treat a different course as the same seat', function (): void {
    ['student' => $student, 'oldPlan' => $oldPlan] = movedAndPaid($this->tenant, $this->course);
    // The old plan is for another course the student also owes for.
    $oldPlan->batch->update(['course_id' => Course::factory()->for($this->tenant)->create()->id]);

    Artisan::call('fees:reconcile-blocks');

    expect(activeBlocks($student))->toBe(1);
});

it('never lifts a block in another company because of this one', function (): void {
    ['student' => $paidStudent] = movedAndPaid($this->tenant, $this->course);

    $other = Tenant::factory()->create();
    $otherStudent = User::factory()->for($other)->create(['user_type' => 'student']);
    $otherBatch = Batch::factory()->for($other)->create();
    $otherPlan = FeePlan::factory()->for($other)->create(['user_id' => $otherStudent->id, 'batch_id' => $otherBatch->id, 'status' => FeePlanStatus::Active->value]);
    Instalment::factory()->for($other)->create([
        'fee_plan_id' => $otherPlan->id, 'seq' => 1, 'amount_paise' => 1_000_000,
        'due_on' => Carbon::today()->subDays(12)->toDateString(), 'status' => InstalmentStatus::Pending->value,
    ]);
    AccessBlock::factory()->for($other)->create(['user_id' => $otherStudent->id, 'batch_id' => $otherBatch->id, 'fee_plan_id' => $otherPlan->id]);

    Artisan::call('fees:reconcile-blocks');

    expect(activeBlocks($paidStudent))->toBe(0)->and(activeBlocks($otherStudent))->toBe(1);
});
