<?php

declare(strict_types=1);

use App\Actions\Advice\CalibratePri;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PriCalibration;
use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

/** A student in the outcome cohort with a cached score + a placement outcome. */
function outcomeStudent(Tenant $tenant, int $mastery, int $engagement, bool $placed): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);

    return withinTenant($tenant, function () use ($tenant, $student, $mastery, $engagement, $placed) {
        StudentScore::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $student->id,
            'engagement' => $engagement,
            'mastery' => [['module_id' => 1, 'name' => 'M', 'pct' => $mastery, 'band' => 'strong']],
        ]);
        $posting = JobPosting::factory()->for($tenant)->create();
        JobApplication::factory()->for($tenant)->create([
            'job_posting_id' => $posting->id, 'user_id' => $student->id,
            'status' => $placed ? JobApplication::STATUS_PLACED : JobApplication::STATUS_APPLIED,
        ]);

        return $student;
    });
}

it('calibrates PRI weights toward the signal that predicts placement', function () {
    // Placed students have high mastery; not-placed have low. Engagement is flat.
    foreach (range(1, 5) as $i) {
        outcomeStudent($this->tenant, mastery: 90, engagement: 50, placed: true);
        outcomeStudent($this->tenant, mastery: 30, engagement: 50, placed: false);
    }

    $calibration = app(CalibratePri::class)->handle($this->tenant);

    expect($calibration)->not->toBeNull()
        ->and($calibration->applied)->toBeTrue()
        ->and($calibration->cohort_size)->toBe(10)
        // Mastery separated the groups; engagement did not → mastery weight dominates.
        ->and($calibration->weights['mastery'])->toBeGreaterThan($calibration->weights['engagement'])
        ->and(array_sum($calibration->weights))->toEqualWithDelta(1.0, 0.001);
});

it('does not calibrate below the minimum cohort', function () {
    foreach (range(1, 2) as $i) {
        outcomeStudent($this->tenant, mastery: 90, engagement: 50, placed: true);
        outcomeStudent($this->tenant, mastery: 30, engagement: 50, placed: false);
    }

    expect(app(CalibratePri::class)->handle($this->tenant))->toBeNull();
    expect(PriCalibration::withoutGlobalScopes()->count())->toBe(0);
});

it('does not calibrate when one outcome group is too small', function () {
    foreach (range(1, 9) as $i) {
        outcomeStudent($this->tenant, mastery: 80, engagement: 50, placed: true);
    }
    outcomeStudent($this->tenant, mastery: 40, engagement: 50, placed: false); // only 1 not-placed

    expect(app(CalibratePri::class)->handle($this->tenant))->toBeNull();
});

it('exposes the active weights only within the owning tenant', function () {
    $calibration = withinTenant($this->tenant, fn () => PriCalibration::factory()->create([
        'tenant_id' => $this->tenant->id,
        'weights' => ['mastery' => 0.7, 'engagement' => 0.2, 'attendance' => 0.1],
    ]));

    withinTenant($this->tenant, function () {
        expect(PriCalibration::activeWeights())->toBe(['mastery' => 0.7, 'engagement' => 0.2, 'attendance' => 0.1]);
    });

    $other = Tenant::factory()->create();
    withinTenant($other, function () {
        expect(PriCalibration::activeWeights())->toBeNull(); // another tenant sees no calibration
    });
});
