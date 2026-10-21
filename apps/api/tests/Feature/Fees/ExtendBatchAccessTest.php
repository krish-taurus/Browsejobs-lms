<?php

declare(strict_types=1);

use App\Actions\Fees\ExtendBatchAccess;
use App\Actions\Fees\RunDunningLadder;
use App\Enums\AccessBlockLevel;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fees\DuesFeeGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['fees.ladder.grace_days' => 2, 'fees.ladder.hard_block_after_days' => 7]);
    $this->tenant = Tenant::factory()->create();
    $this->course = Course::factory()->for($this->tenant)->create();
    $this->batch = paidBatch($this->tenant, $this->course, 'DE-202610-103');
    $this->other = paidBatch($this->tenant, $this->course, 'DE-202610-104');
});

function paidBatch(Tenant $tenant, Course $course, string $number): Batch
{
    return Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'number' => $number, 'type' => BatchType::Paid->value]);
}

/** A student seated in a paid batch who has not paid and has no fee plan: only the grace period lets them in. */
function unpaidStudent(Tenant $tenant, Batch $batch, int $seatedDaysAgo = 20): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $member = BatchMember::factory()->for($tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::PaymentPending->value]);
    DB::table('batch_members')->where('id', $member->id)->update(['updated_at' => now()->subDays($seatedDaysAgo)]);

    return $student;
}

function gate(): DuesFeeGate
{
    return app(DuesFeeGate::class);
}

it('extends a whole batch from the command, by id or by number, and says who it covers', function (): void {
    unpaidStudent($this->tenant, $this->batch);
    unpaidStudent($this->tenant, $this->batch);

    Artisan::call('fees:extend-batch-access', ['batch' => (string) $this->batch->id, '--days' => 10, '--reason' => 'Payment link issue']);
    $output = Artisan::output();

    $batch = $this->batch->fresh();
    expect($batch->access_extended_until->toDateString())->toBe(Carbon::today()->addDays(10)->toDateString())
        ->and($batch->access_extended_reason)->toBe('Payment link issue')
        ->and($output)->toContain('DE-202610-103')->and($output)->toContain('2 students');

    // By number too.
    Artisan::call('fees:extend-batch-access', ['batch' => 'DE-202610-104', '--days' => 3]);
    expect($this->other->fresh()->access_extended_until->toDateString())->toBe(Carbon::today()->addDays(3)->toDateString());
});

it('adds up when a running extension is extended again', function (): void {
    app(ExtendBatchAccess::class)->handle($this->batch, 10, 'first');
    $second = app(ExtendBatchAccess::class)->handle($this->batch->fresh(), 5, 'second');

    expect($second['until']->toDateString())->toBe(Carbon::today()->addDays(15)->toDateString())
        ->and($this->batch->fresh()->access_extended_reason)->toBe('second');
});

it('refuses a day count outside 1 to 30', function (int $days): void {
    expect(fn () => app(ExtendBatchAccess::class)->handle($this->batch, $days))->toThrow(ValidationException::class);
    expect($this->batch->fresh()->access_extended_until)->toBeNull();
})->with([0, 31, -3]);

it('says plainly when the batch does not exist', function (): void {
    expect(Artisan::call('fees:extend-batch-access', ['batch' => '999999']))->toBe(1)
        ->and(Artisan::output())->toContain('not found');
});

it('lets unpaid students with no fee plan into class while it runs, and only in that batch', function (): void {
    $mine = unpaidStudent($this->tenant, $this->batch);
    $theirs = unpaidStudent($this->tenant, $this->other);
    expect(gate()->allowsLiveAccess($mine, $this->batch->fresh()))->toBeFalse();

    app(ExtendBatchAccess::class)->handle($this->batch, 10, 'extension');

    expect(gate()->allowsLiveAccess($mine, $this->batch->fresh()))->toBeTrue()
        ->and(gate()->allowsLiveAccess($theirs, $this->other->fresh()))->toBeFalse();
});

it('locks them out again by itself the day after it ends', function (): void {
    $student = unpaidStudent($this->tenant, $this->batch);
    app(ExtendBatchAccess::class)->handle($this->batch, 10, 'extension');
    expect(gate()->allowsLiveAccess($student, $this->batch->fresh()))->toBeTrue();

    $this->travel(10)->days(); // its last day: still open
    expect(gate()->allowsLiveAccess($student, $this->batch->fresh()))->toBeTrue();

    $this->travel(1)->days();
    expect(gate()->allowsLiveAccess($student, $this->batch->fresh()))->toBeFalse();
});

it('lifts the fee blocks already applied in that batch, and no other', function (): void {
    $blocked = unpaidStudent($this->tenant, $this->batch);
    $elsewhere = unpaidStudent($this->tenant, $this->other);
    foreach ([[$blocked, $this->batch], [$elsewhere, $this->other]] as [$student, $batch]) {
        AccessBlock::factory()->for($this->tenant)->create(['user_id' => $student->id, 'batch_id' => $batch->id, 'level' => AccessBlockLevel::Hard->value]);
    }

    $result = app(ExtendBatchAccess::class)->handle($this->batch, 10, 'extension');

    expect($result['blocks_lifted'])->toBe(1)
        ->and(AccessBlock::withoutGlobalScopes()->where('user_id', $blocked->id)->whereNull('lifted_at')->count())->toBe(0)
        ->and(AccessBlock::withoutGlobalScopes()->where('user_id', $elsewhere->id)->whereNull('lifted_at')->count())->toBe(1)
        ->and(gate()->allowsLiveAccess($blocked, $this->batch->fresh()))->toBeTrue();
});

/** A student on a fee plan with an instalment that fell due `$daysLate` days ago. */
function overdueOn(object $test, Batch $batch, int $daysLate): User
{
    $student = User::factory()->for($test->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 0'.random_int(1000, 9999), 'email' => 'late'.random_int(1, 999999).'@example.com']);
    BatchMember::factory()->for($test->tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::Enrolled->value]);
    $plan = FeePlan::factory()->for($test->tenant)->create(['user_id' => $student->id, 'batch_id' => $batch->id, 'status' => FeePlanStatus::Active->value]);
    Instalment::factory()->for($test->tenant)->create([
        'fee_plan_id' => $plan->id, 'seq' => 1, 'amount_paise' => 3_000_000,
        'due_on' => Carbon::today()->subDays($daysLate)->toDateString(), 'status' => InstalmentStatus::Pending->value,
    ]);

    return $student;
}

it('stops the dunning ladder blocking anyone in the batch while the extension runs', function (): void {
    $late = overdueOn($this, $this->batch, 20);   // well past grace + hard-block days
    $lateElsewhere = overdueOn($this, $this->other, 20);

    app(ExtendBatchAccess::class)->handle($this->batch, 10, 'extension');
    $summary = app(RunDunningLadder::class)->handle();

    expect(AccessBlock::withoutGlobalScopes()->where('user_id', $late->id)->whereNull('lifted_at')->count())->toBe(0)
        ->and(AccessBlock::withoutGlobalScopes()->where('user_id', $lateElsewhere->id)->whereNull('lifted_at')->count())->toBe(1)
        ->and($summary['hard_blocks'])->toBe(1);

    // After it ends the ladder blocks them again, by itself.
    $this->travel(11)->days();
    app(RunDunningLadder::class)->handle();
    expect(AccessBlock::withoutGlobalScopes()->where('user_id', $late->id)->whereNull('lifted_at')->count())->toBe(1);
});

it('leaves another company\'s batch alone', function (): void {
    $otherTenant = Tenant::factory()->create();
    $theirBatch = paidBatch($otherTenant, Course::factory()->for($otherTenant)->create(), 'DE-202610-900');
    $theirStudent = unpaidStudent($otherTenant, $theirBatch);

    app(ExtendBatchAccess::class)->handle($this->batch, 10, 'extension');

    expect($theirBatch->fresh()->access_extended_until)->toBeNull()
        ->and(gate()->allowsLiveAccess($theirStudent, $theirBatch->fresh()))->toBeFalse();
});
