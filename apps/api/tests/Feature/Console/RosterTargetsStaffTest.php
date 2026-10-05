<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;

/**
 * A batch seat is not always held by someone with user_type=student — a trainer
 * sitting the course, or an internal test seat, is staff. The CRM renders them
 * on the roster, so roster actions must be able to target them; refusing with
 * "Student 'N' not found" left a visible row unmanageable.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->staff = User::factory()->for($this->tenant)->create([
        'user_type' => 'staff', 'name' => 'Coach On Course', 'phone' => '+919000000123',
    ]);

    withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-77', 'type' => BatchType::Paid->value]);
        BatchMember::query()->create(['batch_id' => $batch->id, 'user_id' => $this->staff->id, 'status' => 'enrolled']);
    });
});

it('updates the profile of a staff member sitting in a batch', function () {
    $this->artisan('student:update', [
        'user' => (string) $this->staff->id,
        '--name' => 'Coach Renamed',
    ])->assertExitCode(0);

    expect($this->staff->fresh()->name)->toBe('Coach Renamed');
});

it('finds that same seat by phone number', function () {
    $this->artisan('student:update', [
        'user' => '+919000000123',
        '--name' => 'Found By Phone',
    ])->assertExitCode(0);

    expect($this->staff->fresh()->name)->toBe('Found By Phone');
});

it('still resolves an ordinary student normally', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+919000000999']);

    $this->artisan('student:update', ['user' => '+919000000999', '--name' => 'Real Student'])
        ->assertExitCode(0);

    expect($student->fresh()->name)->toBe('Real Student');
});
