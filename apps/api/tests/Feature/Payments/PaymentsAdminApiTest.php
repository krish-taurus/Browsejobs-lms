<?php

declare(strict_types=1);

use App\Enums\FeePlanType;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fees\DuesFeeGate;
use App\Support\Fees\FeeGate;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

it('lists fee plans scoped to the tenant', function () {
    $mine = FeePlan::factory()->for($this->tenant)->create();
    FeePlan::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/fee-plans')
        ->assertOk()
        ->assertJsonFragment(['id' => $mine->id])
        ->assertJsonCount(1, 'data');
});

it('404s a foreign fee plan on show', function () {
    $foreign = FeePlan::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/admin/fee-plans/{$foreign->id}")->assertNotFound();
});

it('previews an EMI schedule', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/fee-plans/preview', ['type' => 'emi', 'emi_count' => 3])
        ->assertOk()
        ->assertJsonPath('data.total_paise', 3_000_000)
        ->assertJsonCount(3, 'data.instalments');
});

it('creates a fee plan for a Reserved member and lists candidates', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/admin/fee-plans/candidates?batch_id={$batch->id}")
        ->assertOk()
        ->assertJsonFragment(['user_id' => $student->id]);

    $this->postJson('/api/v1/admin/fee-plans', [
        'user_id' => $student->id, 'batch_id' => $batch->id, 'type' => 'emi', 'emi_count' => 3,
    ])->assertCreated()->assertJsonPath('data.type', 'emi');

    expect(FeePlan::withoutGlobalScopes()->where('batch_id', $batch->id)->exists())->toBeTrue();
});

it('generates a payment link for an instalment through the API', function () {
    $plan = FeePlan::factory()->for($this->tenant)->create(['type' => FeePlanType::Single->value]);
    $inst = Instalment::factory()->for($this->tenant)->create(['fee_plan_id' => $plan->id]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/instalments/{$inst->id}/link")
        ->assertOk()
        ->assertJsonPath('data.payment_link_url', fn ($url) => str_contains((string) $url, 'rzp.test'));
});

it('denies a staff user without manage-fees', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');

    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/fee-plans')->assertForbidden();
});

it('binds the real DuesFeeGate: allows a clear student, denies a blocked one (P2.3)', function () {
    expect(app(FeeGate::class))->toBeInstanceOf(DuesFeeGate::class);

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $batch = Batch::factory()->for($this->tenant)->create();

    expect(app(FeeGate::class)->allowsLiveAccess($student, $batch))->toBeTrue();

    AccessBlock::factory()->for($this->tenant)->create(['user_id' => $student->id]);

    expect(app(FeeGate::class)->allowsLiveAccess($student, $batch))->toBeFalse();
});
