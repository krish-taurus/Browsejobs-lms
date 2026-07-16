<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->tenant = Tenant::factory()->create();
    $this->batch = Batch::factory()->for($this->tenant)->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($this->tenant)->create([
        'batch_id' => $this->batch->id, 'user_id' => $this->student->id, 'status' => BatchMemberStatus::Enrolled->value,
    ]);
});

it('returns the student their own fee status with a pay link', function () {
    $plan = FeePlan::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'batch_id' => $this->batch->id, 'status' => FeePlanStatus::Active->value,
    ]);
    Instalment::factory()->for($this->tenant)->create([
        'fee_plan_id' => $plan->id, 'seq' => 1, 'amount_paise' => 3_000_000,
        'due_on' => Carbon::today()->addDays(3)->toDateString(), 'status' => InstalmentStatus::Pending->value,
    ]);

    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/fee-status')
        ->assertOk()
        ->assertJsonPath('data.has_plan', true)
        ->assertJsonPath('data.next_amount_paise', 3_000_000)
        ->assertJsonPath('data.days_to_due', 3)
        ->assertJsonPath('data.block', null)
        ->assertJsonPath('data.pay_url', fn ($url) => str_contains((string) $url, 'rzp.test'));
});

it('reports an active block', function () {
    $plan = FeePlan::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'batch_id' => $this->batch->id, 'status' => FeePlanStatus::Active->value,
    ]);
    Instalment::factory()->for($this->tenant)->create([
        'fee_plan_id' => $plan->id, 'seq' => 1, 'due_on' => Carbon::today()->subDays(10)->toDateString(),
        'status' => InstalmentStatus::Pending->value,
    ]);
    AccessBlock::factory()->for($this->tenant)->hard()->create(['user_id' => $this->student->id]);

    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/fee-status')
        ->assertOk()
        ->assertJsonPath('data.block', 'hard')
        ->assertJsonPath('data.overdue', true);
});

it('returns has_plan false for a student with no fee plan', function () {
    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/fee-status')->assertOk()->assertJsonPath('data.has_plan', false);
});

it('does not expose another student\'s plan', function () {
    $plan = FeePlan::factory()->for($this->tenant)->create([
        'user_id' => $this->student->id, 'batch_id' => $this->batch->id,
    ]);
    Instalment::factory()->for($this->tenant)->create(['fee_plan_id' => $plan->id, 'seq' => 1]);

    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($other);

    $this->getJson('/api/v1/me/fee-status')->assertOk()->assertJsonPath('data.has_plan', false);
});
