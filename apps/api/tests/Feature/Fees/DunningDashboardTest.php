<?php

declare(strict_types=1);

use App\Enums\FeePlanStatus;
use App\Models\AccessBlock;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

function feePlanWithInstalment(Tenant $tenant, string $dueOn, string $status = 'pending', int $amount = 1_000_000): FeePlan
{
    $plan = FeePlan::factory()->for($tenant)->create(['status' => FeePlanStatus::Active->value]);
    Instalment::factory()->for($tenant)->create([
        'fee_plan_id' => $plan->id, 'seq' => 1, 'amount_paise' => $amount, 'due_on' => $dueOn, 'status' => $status,
    ]);

    return $plan;
}

it('aggregates aging, expected collections and the overdue worklist', function () {
    feePlanWithInstalment($this->tenant, Carbon::today()->subDays(3)->toDateString());   // 0-7 bucket
    feePlanWithInstalment($this->tenant, Carbon::today()->subDays(20)->toDateString());  // 8-30 bucket
    feePlanWithInstalment($this->tenant, Carbon::today()->addDays(5)->toDateString());   // not overdue
    $blockedPlan = feePlanWithInstalment($this->tenant, Carbon::today()->subDays(40)->toDateString()); // 30+ bucket
    AccessBlock::factory()->for($this->tenant)->create(['user_id' => $blockedPlan->user_id]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/dunning')
        ->assertOk()
        ->assertJsonPath('data.overdue_count', 3)
        ->assertJsonPath('data.aging.bucket_0_7', 1)
        ->assertJsonPath('data.aging.bucket_8_30', 1)
        ->assertJsonPath('data.aging.bucket_30_plus', 1)
        ->assertJsonPath('data.active_blocks_count', 1)
        ->assertJsonPath('data.expected_collections_paise', 4_000_000); // 4 unpaid instalments
});

it('is gated by manage-fees', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/dunning')->assertForbidden();
});

it('scopes the dashboard to the tenant', function () {
    feePlanWithInstalment(Tenant::factory()->create(), Carbon::today()->subDays(10)->toDateString());

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/dunning')->assertOk()->assertJsonPath('data.overdue_count', 0);
});
