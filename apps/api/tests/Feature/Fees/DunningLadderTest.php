<?php

declare(strict_types=1);

use App\Actions\Fees\RunDunningLadder;
use App\Enums\AccessBlockLevel;
use App\Enums\BatchMemberStatus;
use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\CrmTask;
use App\Models\FeePlan;
use App\Models\FeeReminder;
use App\Models\Instalment;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->today = Carbon::parse('2026-08-01');
});

/**
 * @return array{plan: FeePlan, student: User, instalment: Instalment}
 */
function overduePlan(Tenant $tenant, string $dueOn, string $phone = '+91 90000 00001', ?string $email = 'ladder@example.com'): array
{
    $batch = Batch::factory()->for($tenant)->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => $phone, 'email' => $email]);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::Enrolled->value,
    ]);
    $plan = FeePlan::factory()->for($tenant)->create([
        'user_id' => $student->id, 'batch_id' => $batch->id, 'status' => FeePlanStatus::Active->value,
    ]);
    $instalment = Instalment::factory()->for($tenant)->create([
        'fee_plan_id' => $plan->id, 'seq' => 1, 'amount_paise' => 3_000_000,
        'due_on' => $dueOn, 'status' => InstalmentStatus::Pending->value,
    ]);

    return compact('plan', 'student', 'instalment');
}

it('sends the T-7 reminder once and dedupes on re-run', function () {
    ['instalment' => $inst] = overduePlan($this->tenant, $this->today->copy()->addDays(7)->toDateString());

    $first = app(RunDunningLadder::class)->handle($this->today);
    $second = app(RunDunningLadder::class)->handle($this->today);

    expect($first['reminders'])->toBe(1)
        ->and($second['reminders'])->toBe(0)
        ->and(FeeReminder::withoutGlobalScopes()->where('instalment_id', $inst->id)->where('rung', 't-7')->count())->toBe(1);
});

it('sends a daily grace reminder while overdue within the grace window', function () {
    ['instalment' => $inst] = overduePlan($this->tenant, $this->today->copy()->subDays(2)->toDateString());

    app(RunDunningLadder::class)->handle($this->today);

    expect(FeeReminder::withoutGlobalScopes()->where('instalment_id', $inst->id)->where('rung', 'grace-2')->count())->toBe(1);
});

it('applies a soft block once the grace window closes', function () {
    ['student' => $student] = overduePlan($this->tenant, $this->today->copy()->subDays(5)->toDateString()); // grace_days = 5

    $summary = app(RunDunningLadder::class)->handle($this->today);

    $block = AccessBlock::withoutGlobalScopes()->where('user_id', $student->id)->whereNull('lifted_at')->first();
    expect($summary['soft_blocks'])->toBe(1)
        ->and($block)->not->toBeNull()
        ->and($block->level)->toBe(AccessBlockLevel::Soft);
});

it('promotes to a hard block after the window and raises a counselor task', function () {
    $counselor = counselorIn($this->tenant);
    ['student' => $student] = overduePlan($this->tenant, $this->today->copy()->subDays(12)->toDateString()); // 5 + 7

    // A matching CRM lead assigned to the counselor.
    Lead::factory()->for($this->tenant)->create([
        'phone' => $student->phone,
        'phone_normalized' => PhoneNormalizer::normalize($student->phone),
        'email' => $student->email,
        'assigned_to' => $counselor->id,
    ]);

    $summary = app(RunDunningLadder::class)->handle($this->today);

    $block = AccessBlock::withoutGlobalScopes()->where('user_id', $student->id)->whereNull('lifted_at')->first();
    expect($summary['hard_blocks'])->toBe(1)
        ->and($block->level)->toBe(AccessBlockLevel::Hard)
        ->and(CrmTask::withoutGlobalScopes()->where('assigned_to', $counselor->id)->count())->toBe(1);
});

it('never blocks a not-yet-due plan', function () {
    ['student' => $student] = overduePlan($this->tenant, $this->today->copy()->addDays(10)->toDateString());

    app(RunDunningLadder::class)->handle($this->today);

    expect(AccessBlock::withoutGlobalScopes()->where('user_id', $student->id)->count())->toBe(0);
});

it('does not block when the instalment is paid', function () {
    ['student' => $student, 'instalment' => $inst] = overduePlan($this->tenant, $this->today->copy()->subDays(20)->toDateString());
    $inst->update(['status' => InstalmentStatus::Paid->value]);
    FeePlan::withoutGlobalScopes()->where('user_id', $student->id)->update(['status' => FeePlanStatus::Paid->value]);

    app(RunDunningLadder::class)->handle($this->today);

    expect(AccessBlock::withoutGlobalScopes()->where('user_id', $student->id)->count())->toBe(0);
});
