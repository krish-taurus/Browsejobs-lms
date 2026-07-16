<?php

declare(strict_types=1);

use App\Actions\Conversion\CompleteBootcamp;
use App\Enums\BatchMemberStatus;
use App\Models\AuditLog;
use App\Models\BatchMember;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    withinTenant($this->tenant, fn () => LeadStage::query()->create(['name' => 'Reserved', 'slug' => 'reserved', 'position' => 5]));
});

it('converts every occupying attendee and marks the bootcamp completed', function () {
    ['bootcamp' => $bootcamp, 'paid' => $paid] = bootcampConversionSetup($this->tenant);

    // A second occupying attendee + a dropped one (should be skipped).
    $second = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 44444']);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $bootcamp->id, 'user_id' => $second->id, 'status' => BatchMemberStatus::Enrolled->value]);
    $dropped = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $bootcamp->id, 'user_id' => $dropped->id, 'status' => BatchMemberStatus::Dropped->value]);

    $summary = withinTenant($this->tenant, fn () => app(CompleteBootcamp::class)->handle($bootcamp));

    expect($summary['converted'])->toBe(2)
        ->and($bootcamp->fresh()->status)->toBe('completed')
        ->and(BatchMember::withoutGlobalScopes()->where('batch_id', $paid->id)->where('status', BatchMemberStatus::Reserved->value)->count())->toBe(2)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'batch.completed')->count())->toBe(1);
});

it('refuses to complete a non-bootcamp batch', function () {
    ['paid' => $paid] = bootcampConversionSetup($this->tenant);

    expect(fn () => withinTenant($this->tenant, fn () => app(CompleteBootcamp::class)->handle($paid)))
        ->toThrow(ValidationException::class);
});
