<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\EnrolmentType;
use App\Enums\LiveSessionStatus;
use App\Enums\RecordingStatus;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\Recording;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    [$this->batch, $this->session, $this->recording] = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da']);
        $batch = $course->batches()->create(['number' => 'DA-1', 'type' => BatchType::Paid->value]);
        BatchMember::query()->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled']);

        // Inside the pre-class join window (join_open_minutes) so the join
        // endpoint hands out the link.
        $session = LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'SQL joins', 'scheduled_start' => now()->addMinutes(5),
            'status' => LiveSessionStatus::Scheduled->value, 'zoom_join_url' => 'https://zoom.test/j/123',
        ]);
        $past = LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Pandas', 'scheduled_start' => now()->subDays(2),
            'status' => LiveSessionStatus::Ended->value,
        ]);
        $rec = Recording::query()->create([
            'live_session_id' => $past->id, 'title' => 'Pandas',
            'play_url' => 'https://zoom.test/play/pandas', 'passcode' => 'abc123',
            'duration_seconds' => 3600, 'status' => RecordingStatus::Stored->value,
        ]);

        return [$batch, $session, $rec];
    });
});

it('lists the student classes', function () {
    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/classes')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'SQL joins'); // newest first
});

it('never lists funnel masterclass sessions to enrolled students', function () {
    // A leftover marketing masterclass on the (converted) paid batch must not
    // resurface as the student's "next class".
    withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'title' => 'Masterclass — Data', 'kind' => LiveSession::KIND_MASTERCLASS,
        'scheduled_start' => now()->addDay(), 'status' => LiveSessionStatus::Scheduled->value,
    ]));

    Sanctum::actingAs($this->student);

    $titles = $this->getJson('/api/v1/me/classes')->assertOk()->assertJsonCount(2, 'data')
        ->json('data.*.title');

    expect($titles)->not->toContain('Masterclass — Data');
});

it('includes the class topic for the schedule view', function () {
    withinTenant($this->tenant, function () {
        $module = Module::query()->create([
            'tenant_id' => $this->tenant->id, 'course_id' => $this->batch->course_id, 'name' => 'SQL', 'position' => 1,
        ]);
        $topic = Topic::query()->create([
            'tenant_id' => $this->tenant->id, 'module_id' => $module->id, 'name' => 'Window functions', 'position' => 1,
        ]);
        $this->session->update(['topic_id' => $topic->id]);
    });

    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/classes')->assertOk()
        ->assertJsonPath('data.0.topic', 'Window functions');
});

it('does not leak the raw join url in the list', function () {
    Sanctum::actingAs($this->student);

    $body = $this->getJson('/api/v1/me/classes')->assertOk();
    expect($body->getContent())->not->toContain('zoom.test/j/123');
});

it('hands out the join url to an enrolled student', function () {
    Sanctum::actingAs($this->student);

    $this->postJson("/api/v1/me/classes/{$this->session->id}/join")
        ->assertOk()->assertJsonPath('data.join_url', 'https://zoom.test/j/123');
});

it('refuses to let a non-member join', function () {
    $outsider = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($outsider);

    $this->postJson("/api/v1/me/classes/{$this->session->id}/join")->assertStatus(422);
});

it('refuses a self-paced student a live class', function () {
    withinTenant($this->tenant, fn () => BatchMember::withoutGlobalScopes()
        ->where('user_id', $this->student->id)->update(['enrolment_type' => EnrolmentType::SelfPaced->value]));

    Sanctum::actingAs($this->student);
    $this->postJson("/api/v1/me/classes/{$this->session->id}/join")->assertStatus(422);
});

it('lists the student recordings', function () {
    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/recordings')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.class', 'Pandas')
        ->assertJsonPath('data.0.batch_number', 'DA-1'); // grouped by batch on the student page
});

it('hands an enrolled student the Zoom cloud watch url and passcode', function () {
    Sanctum::actingAs($this->student);

    $this->getJson("/api/v1/me/recordings/{$this->recording->id}/download")
        ->assertOk()
        ->assertJsonPath('data.watch_url', 'https://zoom.test/play/pandas')
        ->assertJsonPath('data.passcode', 'abc123');
});

it('denies a recording download to a non-member', function () {
    $outsider = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($outsider);

    $this->getJson("/api/v1/me/recordings/{$this->recording->id}/download")->assertNotFound();
});

it('lets a self-paced student download a recording', function () {
    withinTenant($this->tenant, fn () => BatchMember::withoutGlobalScopes()
        ->where('user_id', $this->student->id)->update(['enrolment_type' => EnrolmentType::SelfPaced->value]));

    Sanctum::actingAs($this->student);
    // Recordings are included for self-paced (unlike live classes).
    $this->getJson("/api/v1/me/recordings/{$this->recording->id}/download")->assertOk();
});

it('does not show another batch classes or recordings', function () {
    $otherStudent = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($otherStudent);

    $this->getJson('/api/v1/me/classes')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/me/recordings')->assertOk()->assertJsonCount(0, 'data');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/me/classes')->assertUnauthorized();
});
