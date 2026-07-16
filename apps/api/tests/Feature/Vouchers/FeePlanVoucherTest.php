<?php

declare(strict_types=1);

use App\Enums\VoucherIssueStatus;
use App\Enums\VoucherType;
use App\Models\FeePlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherIssue;
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

it('pre-applies a voucher: server-owned discount overrides the client value', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);
    $voucher = Voucher::factory()->for($this->tenant)->create(['type' => VoucherType::Flat->value, 'value' => 200_000]);
    $issue = VoucherIssue::factory()->for($this->tenant)->create(['voucher_id' => $voucher->id, 'user_id' => $student->id]);

    Sanctum::actingAs($this->admin);

    // The hint endpoint surfaces the available voucher.
    $this->getJson("/api/v1/admin/fee-plans/voucher?user_id={$student->id}&batch_id={$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.discount_paise', 200_000)
        ->assertJsonPath('data.code', $issue->code);

    $this->postJson('/api/v1/admin/fee-plans', [
        'user_id' => $student->id, 'batch_id' => $batch->id, 'type' => 'single',
        'voucher_code' => $issue->code, 'discount_paise' => 999_999, // client value must be ignored
    ])->assertCreated()->assertJsonPath('data.discount_paise', 200_000);

    $plan = FeePlan::withoutGlobalScopes()->where('batch_id', $batch->id)->firstOrFail();
    expect($plan->discount_paise)->toBe(200_000)
        ->and($plan->instalments()->where('seq', 1)->value('amount_paise'))->toBe(3_000_000 - 200_000)
        ->and($issue->fresh()->status)->toBe(VoucherIssueStatus::Applied)
        ->and($issue->fresh()->fee_plan_id)->toBe($plan->id);
});

it('rejects an invalid voucher code', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/fee-plans', [
        'user_id' => $student->id, 'batch_id' => $batch->id, 'type' => 'single', 'voucher_code' => 'NOPE123456',
    ])->assertStatus(422);
});
