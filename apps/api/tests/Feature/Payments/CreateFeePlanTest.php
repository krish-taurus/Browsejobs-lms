<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Enums\FeePlanType;
use App\Enums\LedgerDirection;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

it('builds an EMI plan with instalments and ledger debits, and audits it', function () {
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    $plan = withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $student, $batch, FeePlanType::Emi, 3,
    ));

    expect($plan->instalments)->toHaveCount(3)
        ->and($plan->total_paise)->toBe(3_000_000);

    $debits = LedgerEntry::withoutGlobalScopes()
        ->where('fee_plan_id', $plan->id)
        ->where('direction', LedgerDirection::Debit->value)
        ->get();

    expect($debits)->toHaveCount(3)
        ->and($debits->sum('amount_paise'))->toBe(3_000_000)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'fees.plan_created')->count())->toBe(1);
});

it('refuses to raise a plan for a student who is not Reserved in the batch', function () {
    ['batch' => $batch] = reservedMemberIn($this->tenant);
    $stranger = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    expect(fn () => withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $stranger, $batch, FeePlanType::Single, 1,
    )))->toThrow(ValidationException::class);
});

it('refuses a cross-tenant batch', function () {
    ['student' => $student] = reservedMemberIn($this->tenant);
    ['batch' => $foreignBatch] = reservedMemberIn(Tenant::factory()->create());

    expect(fn () => withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $student, $foreignBatch, FeePlanType::Single, 1,
    )))->toThrow(ValidationException::class);
});
