<?php

declare(strict_types=1);

use App\Actions\Conversion\ConvertBootcampAttendee;
use App\Enums\BatchMemberStatus;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Support\Crm\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->reservedStage = withinTenant($this->tenant, fn () => LeadStage::query()->create([
        'name' => 'Reserved', 'slug' => 'reserved', 'position' => 5,
    ]));
});

it('assigns the attendee Reserved in the paid batch and flips their lead to reserved', function () {
    ['paid' => $paid, 'student' => $student, 'member' => $bootcampMember] = bootcampConversionSetup($this->tenant);
    $lead = Lead::factory()->for($this->tenant)->create([
        'phone' => $student->phone, 'phone_normalized' => PhoneNormalizer::normalize($student->phone),
    ]);

    $paidMember = withinTenant($this->tenant, fn () => app(ConvertBootcampAttendee::class)->handle($bootcampMember));

    expect($paidMember->batch_id)->toBe($paid->id)
        ->and($paidMember->status)->toBe(BatchMemberStatus::Reserved)
        ->and($lead->fresh()->lead_stage_id)->toBe($this->reservedStage->id)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'conversion.assigned')->count())->toBe(1);
});

it('is idempotent — re-converting keeps a single reserved membership', function () {
    ['paid' => $paid, 'student' => $student, 'member' => $bootcampMember] = bootcampConversionSetup($this->tenant);

    withinTenant($this->tenant, fn () => app(ConvertBootcampAttendee::class)->handle($bootcampMember));
    withinTenant($this->tenant, fn () => app(ConvertBootcampAttendee::class)->handle($bootcampMember));

    expect(BatchMember::withoutGlobalScopes()->where('batch_id', $paid->id)->where('user_id', $student->id)->count())->toBe(1);
});

it('throws when the bootcamp has no linked paid batch', function () {
    ['member' => $bootcampMember, 'paid' => $paid] = bootcampConversionSetup($this->tenant);
    $paid->update(['linked_source_batch_id' => null]); // unlink

    expect(fn () => withinTenant($this->tenant, fn () => app(ConvertBootcampAttendee::class)->handle($bootcampMember)))
        ->toThrow(ValidationException::class);
});

it('scopes conversion to the tenant', function () {
    ['paid' => $paid] = bootcampConversionSetup($this->tenant);

    withinTenant(Tenant::factory()->create(), function () use ($paid) {
        expect(Batch::query()->find($paid->id))->toBeNull();
    });
});
