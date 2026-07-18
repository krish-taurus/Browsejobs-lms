<?php

declare(strict_types=1);

use App\Actions\Advice\GenerateAlumniCheckins;
use App\Models\AlumniCheckin;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

it('schedules a 6-month check-in for a placement that turned six months old', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    withinTenant($this->tenant, function () use ($student) {
        $posting = JobPosting::factory()->for($this->tenant)->create();
        JobApplication::factory()->for($this->tenant)->create([
            'job_posting_id' => $posting->id, 'user_id' => $student->id,
            'status' => JobApplication::STATUS_PLACED, 'placed_at' => now()->subMonths(7),
        ]);

        $created = app(GenerateAlumniCheckins::class)->handle();
        expect($created)->toBe(1);
    });

    $checkin = AlumniCheckin::withoutGlobalScopes()->where('milestone', 'm6')->sole();
    expect($checkin->user_id)->toBe($student->id)->and($checkin->status)->toBe('scheduled');
});

it('is idempotent — a second run creates no duplicate', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    withinTenant($this->tenant, function () use ($student) {
        $posting = JobPosting::factory()->for($this->tenant)->create();
        JobApplication::factory()->for($this->tenant)->create([
            'job_posting_id' => $posting->id, 'user_id' => $student->id,
            'status' => JobApplication::STATUS_PLACED, 'placed_at' => now()->subMonths(7),
        ]);

        app(GenerateAlumniCheckins::class)->handle();
        expect(app(GenerateAlumniCheckins::class)->handle())->toBe(0);
    });

    expect(AlumniCheckin::withoutGlobalScopes()->where('milestone', 'm6')->count())->toBe(1);
});

it('lists and records an alumni response', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $checkin = withinTenant($this->tenant, fn () => AlumniCheckin::factory()->for($this->tenant)->create([
        'user_id' => $student->id, 'milestone' => 'm6',
    ]));
    Sanctum::actingAs($student);

    getJson('/api/v1/me/alumni-checkins')->assertOk()->assertJsonPath('data.0.months', 6);

    postJson("/api/v1/me/alumni-checkins/{$checkin->id}/respond", [
        'still_employed' => true, 'current_company' => 'Acme', 'current_lpa' => 12,
        'would_refer' => true, 'notes' => 'Great outcome.',
    ])->assertOk()->assertJsonPath('data.status', 'responded');

    expect($checkin->fresh()->current_ctc_paise)->toBe(12_00_000 * 100)
        ->and($checkin->fresh()->status)->toBe('responded');

    // Responded check-ins drop out of the pending list.
    getJson('/api/v1/me/alumni-checkins')->assertOk()->assertJsonPath('data', []);
});

it('does not let a student respond to another alumni check-in', function () {
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $checkin = withinTenant($this->tenant, fn () => AlumniCheckin::factory()->for($this->tenant)->create([
        'user_id' => $other->id, 'milestone' => 'm6',
    ]));
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));

    postJson("/api/v1/me/alumni-checkins/{$checkin->id}/respond", [
        'still_employed' => true, 'would_refer' => false,
    ])->assertNotFound();
});

it('requires authentication for the alumni surface', function () {
    getJson('/api/v1/me/alumni-checkins')->assertUnauthorized();
});
