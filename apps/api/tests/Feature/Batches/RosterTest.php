<?php

declare(strict_types=1);

use App\Actions\Batches\CreateBatch;
use App\Actions\Roster\AddStudentToBatch;
use App\Actions\Roster\BulkAddStudentsToBatch;
use App\Actions\Roster\ImportRosterCsv;
use App\Actions\Roster\RemoveBatchMember;
use App\Actions\Roster\TransferBatchMember;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->course = withinTenant($this->tenant, fn () => Course::query()->create([
        'code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de',
    ]));
});

function studentIn(Tenant $tenant): User
{
    return User::factory()->for($tenant)->create(['user_type' => 'student']);
}

function auditCount(string $action): int
{
    return AuditLog::withoutGlobalScopes()->where('action', $action)->count();
}

it('adds a student (silo) and audits it', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);

    $member = app(AddStudentToBatch::class)->handle($batch, studentIn($this->tenant));

    expect($member->status)->toBe(BatchMemberStatus::Reserved)
        ->and(auditCount('roster.member_added'))->toBe(1);
});

it('enforces the capacity guard', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp, ['capacity' => 1]);
    $add = app(AddStudentToBatch::class);

    $add->handle($batch, studentIn($this->tenant));

    expect(fn () => $add->handle($batch, studentIn($this->tenant)))
        ->toThrow(ValidationException::class);
});

it('rejects a duplicate membership', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);
    $student = studentIn($this->tenant);
    $add = app(AddStudentToBatch::class);

    $add->handle($batch, $student);

    expect(fn () => $add->handle($batch, $student))->toThrow(ValidationException::class);
});

it('refuses a student from another tenant', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);
    $foreign = studentIn(Tenant::factory()->create());

    expect(fn () => app(AddStudentToBatch::class)->handle($batch, $foreign))
        ->toThrow(ValidationException::class);
});

it('bulk-adds a selection, skipping duplicates', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);
    $existing = studentIn($this->tenant);
    app(AddStudentToBatch::class)->handle($batch, $existing);

    $students = collect([$existing, studentIn($this->tenant), studentIn($this->tenant)]);
    $summary = app(BulkAddStudentsToBatch::class)->handle($batch, $students);

    expect($summary['added'])->toBe(2)
        ->and($summary['skipped'])->toHaveCount(1);
});

it('imports a CSV, reporting rows it could not add', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);

    $csv = "name,phone,email\n"
        ."Asha,+919000000001,asha@acme.test\n"
        .",,\n"                        // missing name
        ."No Contact,,\n";             // missing phone + email

    $summary = app(ImportRosterCsv::class)->handle($batch, $this->tenant, $csv);

    expect($summary['imported'])->toBe(1)
        ->and($summary['skipped'])->toHaveCount(2)
        ->and($batch->members()->count())->toBe(1);
});

it('transfers a member with reason and audits the move', function () {
    $source = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);
    $target = app(CreateBatch::class)->handle($this->course, BatchType::Paid);
    $member = app(AddStudentToBatch::class)->handle($source, studentIn($this->tenant));

    $moved = app(TransferBatchMember::class)->handle($member, $target, 'Upgraded to paid batch');

    expect($member->fresh()->status)->toBe(BatchMemberStatus::Transferred)
        ->and($moved->batch_id)->toBe($target->id)
        ->and(auditCount('roster.member_transferred'))->toBe(1);
});

it('removes a member with reason and audits it', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp);
    $member = app(AddStudentToBatch::class)->handle($batch, studentIn($this->tenant));

    app(RemoveBatchMember::class)->handle($member, 'Requested withdrawal');

    expect($member->fresh()->status)->toBe(BatchMemberStatus::Dropped)
        ->and(auditCount('roster.member_removed'))->toBe(1);
});

it('frees a seat when a member is removed', function () {
    $batch = app(CreateBatch::class)->handle($this->course, BatchType::Bootcamp, ['capacity' => 1]);
    $add = app(AddStudentToBatch::class);
    $member = $add->handle($batch, studentIn($this->tenant));

    app(RemoveBatchMember::class)->handle($member, 'left');

    // Seat is free again, so a new add succeeds.
    expect($add->handle($batch->fresh(), studentIn($this->tenant)))->not->toBeNull();
});
