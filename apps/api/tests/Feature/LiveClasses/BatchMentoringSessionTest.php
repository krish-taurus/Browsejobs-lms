<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Jobs\CreateZoomMeeting;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Weekly batch mentoring / doubt-clearing sessions on the live-class engine
 * (ADR 0043): the admin allocates a mentor as the session host; the mentor
 * gets the Start button on their teaching board; students see it on their
 * schedule like any class.
 */
beforeEach(function () {
    Queue::fake();
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();

    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    $this->trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Anil']);
    $this->trainer->assignRole('trainer');
    $this->mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'name' => 'Mira']);
    $this->mentor->assignRole('mentor');

    $this->batch = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-1', 'type' => BatchType::Paid->value, 'trainer_id' => $this->trainer->id]);
        $batch->batchMentors()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->mentor->id]);

        return $batch;
    });
});

it('schedules a mentoring session hosted by a batch mentor, with its own Zoom meeting', function () {
    Sanctum::actingAs($this->admin);

    postJson("/api/v1/admin/batches/{$this->batch->id}/sessions", [
        'title' => 'Weekly doubt clearing',
        'scheduled_start' => now()->addDays(2)->toIso8601String(),
        'kind' => 'mentoring',
        'host_user_id' => $this->mentor->id,
    ])->assertCreated()
        ->assertJsonPath('data.kind', 'mentoring')
        ->assertJsonPath('data.host', 'Mira');

    $session = LiveSession::withoutGlobalScopes()->sole();
    expect($session->kind)->toBe('mentoring')
        ->and($session->host_user_id)->toBe($this->mentor->id);

    Queue::assertPushed(CreateZoomMeeting::class); // same engine: auto meeting + recording
});

it('rejects a host who is not on the batch (including another tenant\'s user)', function () {
    $stranger = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $foreign = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'staff']);
    Sanctum::actingAs($this->admin);

    foreach ([$stranger->id, $foreign->id] as $hostId) {
        postJson("/api/v1/admin/batches/{$this->batch->id}/sessions", [
            'title' => 'Weekly doubt clearing',
            'scheduled_start' => now()->addDays(2)->toIso8601String(),
            'kind' => 'mentoring',
            'host_user_id' => $hostId,
        ])->assertUnprocessable();
    }

    expect(LiveSession::withoutGlobalScopes()->count())->toBe(0);
});

it('generates a weekly mentoring series with the mentor as host and no topic mapping', function () {
    Sanctum::actingAs($this->admin);

    $res = postJson("/api/v1/admin/batches/{$this->batch->id}/sessions/series", [
        'weekdays' => [6], // Saturdays
        'time' => '11:00',
        'duration_minutes' => 60,
        'count' => 4,
        'start_date' => now()->addDay()->toDateString(),
        'map_topics' => true, // ignored for mentoring — doubt clearing has no syllabus
        'kind' => 'mentoring',
        'host_user_id' => $this->mentor->id,
    ])->assertCreated();

    expect($res->json('data'))->toHaveCount(4);

    $sessions = LiveSession::withoutGlobalScopes()->get();
    expect($sessions)->toHaveCount(4)
        ->and($sessions->every(fn (LiveSession $s) => $s->kind === 'mentoring' && $s->host_user_id === $this->mentor->id))->toBeTrue()
        ->and($sessions->first()->title)->toBe('Mentoring session 1')
        ->and($sessions->first()->topic_id)->toBeNull();
});

it('puts the mentoring session on the mentor\'s teaching board as theirs, startable in the host window', function () {
    withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'kind' => 'mentoring', 'host_user_id' => $this->mentor->id,
        'title' => 'Weekly doubt clearing', 'scheduled_start' => now()->addMinutes(10),
        'status' => LiveSessionStatus::Scheduled->value,
        'zoom_start_url' => 'https://zoom.test/s/1', 'zoom_join_url' => 'https://zoom.test/j/1',
    ]));

    Sanctum::actingAs($this->mentor);

    getJson('/api/v1/admin/my-teaching')
        ->assertOk()
        ->assertJsonPath('data.upcoming.0.kind', 'mentoring')
        ->assertJsonPath('data.upcoming.0.you_teach', true)
        ->assertJsonPath('data.upcoming.0.can_start', true);
});

it('hands the host mentor the start link, and refuses a staff member who is not the host', function () {
    $session = withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'kind' => 'mentoring', 'host_user_id' => $this->mentor->id,
        'title' => 'Weekly doubt clearing', 'scheduled_start' => now()->addMinutes(10),
        'status' => LiveSessionStatus::Scheduled->value,
        'zoom_start_url' => 'https://zoom.test/s/1', 'zoom_join_url' => 'https://zoom.test/j/1',
    ]));

    // The mentor has no teach-classes permission — hosting is gated by assignment.
    Sanctum::actingAs($this->mentor);
    postJson("/api/v1/admin/sessions/{$session->id}/start")
        ->assertOk()
        ->assertJsonPath('data.start_url', 'https://zoom.test/s/1');

    // The batch's lead trainer is not this session's host.
    Sanctum::actingAs($this->trainer);
    postJson("/api/v1/admin/sessions/{$session->id}/start")->assertUnprocessable();
});

it('denies students the staff start endpoint', function () {
    $session = withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'kind' => 'mentoring', 'host_user_id' => $this->mentor->id,
        'title' => 'Weekly doubt clearing', 'scheduled_start' => now()->addMinutes(10),
        'status' => LiveSessionStatus::Scheduled->value,
        'zoom_start_url' => 'https://zoom.test/s/1',
    ]));

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    postJson("/api/v1/admin/sessions/{$session->id}/start")->assertForbidden();
});

it('404s a mentoring session start from another tenant\'s staff', function () {
    $session = withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'batch_id' => $this->batch->id, 'kind' => 'mentoring', 'host_user_id' => $this->mentor->id,
        'title' => 'Weekly doubt clearing', 'scheduled_start' => now()->addMinutes(10),
        'status' => LiveSessionStatus::Scheduled->value,
        'zoom_start_url' => 'https://zoom.test/s/1',
    ]));

    $otherTenant = Tenant::factory()->create();
    $outsider = User::factory()->for($otherTenant)->create(['user_type' => 'staff']);
    $outsider->assignRole('admin');
    Sanctum::actingAs($outsider);

    postJson("/api/v1/admin/sessions/{$session->id}/start")->assertNotFound();
});
