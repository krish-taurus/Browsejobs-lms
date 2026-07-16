<?php

declare(strict_types=1);

use App\Enums\VoucherIssueStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherIssue;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

it('creates, lists, updates and deactivates a voucher', function () {
    Sanctum::actingAs($this->admin);

    $id = $this->postJson('/api/v1/admin/vouchers', [
        'name' => 'Diwali offer', 'type' => 'percent', 'value' => 1_000, 'max_discount_paise' => 250_000,
        'valid_days' => 20, 'per_user_limit' => 1, 'is_review_reward' => true,
    ])->assertCreated()->json('data.id');

    $this->getJson('/api/v1/admin/vouchers')->assertOk()->assertJsonFragment(['name' => 'Diwali offer']);

    $this->putJson("/api/v1/admin/vouchers/{$id}", ['value' => 1_500])
        ->assertOk()->assertJsonPath('data.value', 1_500);

    $this->deleteJson("/api/v1/admin/vouchers/{$id}")->assertOk();
    expect(Voucher::withoutGlobalScopes()->find($id)->active)->toBeFalse();
});

it('reports redemption analytics', function () {
    $voucher = Voucher::factory()->for($this->tenant)->create();
    VoucherIssue::factory()->for($this->tenant)->create([
        'voucher_id' => $voucher->id, 'user_id' => $this->admin->id,
        'status' => VoucherIssueStatus::Applied->value, 'discount_paise' => 200_000,
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/vouchers/analytics')
        ->assertOk()
        ->assertJsonPath('data.issued', 1)
        ->assertJsonPath('data.applied', 1)
        ->assertJsonPath('data.discount_paise', 200_000);
});

it('denies a staff user without manage-vouchers', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');

    Sanctum::actingAs($mentor);

    $this->getJson('/api/v1/admin/vouchers')->assertForbidden();
});

it('404s a foreign tenant voucher on update', function () {
    $foreign = Voucher::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/admin/vouchers/{$foreign->id}", ['value' => 5])->assertNotFound();
});
