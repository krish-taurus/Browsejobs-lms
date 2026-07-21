<?php

declare(strict_types=1);

use App\Models\Batch;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\Recording;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Storage::fake('s3');
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function aVideoLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'video', 'title' => 'Watch this']);
}

/** A stored recording in the tenant's library (needs a live session to hang off). */
function aStoredRecording(Tenant $tenant, string $path = 'recordings/rec-1.mp4'): Recording
{
    return withinTenant($tenant, function () use ($tenant, $path): Recording {
        $course = Course::factory()->for($tenant)->create();
        $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'trainer_id' => null]);
        $session = LiveSession::query()->create([
            'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'title' => 'Class',
            'scheduled_start' => now()->subHours(2), 'scheduled_end' => now()->subHour(), 'status' => 'ended',
        ]);

        return Recording::query()->create([
            'tenant_id' => $tenant->id, 'live_session_id' => $session->id, 'title' => 'Recording',
            'storage_path' => $path, 'duration_seconds' => 3600, 'status' => 'stored',
        ]);
    });
}

function aCurriculumAdmin(Tenant $tenant): User
{
    $admin = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');

    return $admin;
}

/* ---- Admin surface ---- */

it('attaches an external embed URL to a video lesson', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aVideoLesson($this->tenant);
    Sanctum::actingAs(aCurriculumAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/video", [
        'source' => 'url', 'title' => 'Intro', 'url' => 'https://www.youtube.com/embed/abc123',
    ])
        ->assertOk()
        ->assertJsonPath('data.source', 'url')
        ->assertJsonPath('data.url', 'https://www.youtube.com/embed/abc123')
        ->assertJsonPath('data.playable_url', 'https://www.youtube.com/embed/abc123');

    expect(LessonVideo::withoutGlobalScopes()->where('lesson_id', $lesson->id)->count())->toBe(1);
});

it('attaches a library recording and swaps a previous video in place', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aVideoLesson($this->tenant);
    $recording = aStoredRecording($this->tenant);
    Sanctum::actingAs(aCurriculumAdmin($this->tenant));

    // First attach a URL...
    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'url', 'url' => 'https://vimeo.com/1'])->assertOk();
    // ...then replace it with a recording. Still one row (unique per lesson).
    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'recording', 'recording_id' => $recording->id])
        ->assertOk()
        ->assertJsonPath('data.source', 'recording')
        ->assertJsonPath('data.recording_id', $recording->id)
        ->assertJsonPath('data.url', null);

    expect(LessonVideo::withoutGlobalScopes()->where('lesson_id', $lesson->id)->count())->toBe(1);
});

it('validates the source and its required fields', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aVideoLesson($this->tenant);
    Sanctum::actingAs(aCurriculumAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'url'])->assertStatus(422); // url missing
    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'recording'])->assertStatus(422); // recording_id missing
    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'upload', 'url' => 'https://x/y'])->assertStatus(422); // upload not allowed yet
});

it('rejects a video on a non-video lesson', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aVideoLesson($this->tenant);
    $lesson->update(['type' => 'notes']);
    Sanctum::actingAs(aCurriculumAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'url', 'url' => 'https://x/y'])->assertStatus(422);
});

it('lists the tenant recording library', function () {
    $this->seed(RolePermissionSeeder::class);
    aStoredRecording($this->tenant, 'recordings/a.mp4');
    Sanctum::actingAs(aCurriculumAdmin($this->tenant));

    getJson('/api/v1/admin/recordings-library')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Recording');
});

it('will not attach another tenant\'s recording', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aVideoLesson($this->tenant);
    $foreign = aStoredRecording(Tenant::factory()->create());
    Sanctum::actingAs(aCurriculumAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'recording', 'recording_id' => $foreign->id])
        ->assertNotFound();
});

it('denies video management without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aVideoLesson($this->tenant);
    Sanctum::actingAs(counselorIn($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/video", ['source' => 'url', 'url' => 'https://x/y'])->assertForbidden();
});

/* ---- Student surface ---- */

it('gives the student a playable embed url', function () {
    $lesson = aVideoLesson($this->tenant);
    withinTenant($this->tenant, fn () => LessonVideo::query()->create([
        'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id, 'source' => 'url',
        'title' => 'Intro', 'url' => 'https://www.youtube.com/embed/abc123',
    ]));
    Sanctum::actingAs($this->student);

    getJson("/api/v1/me/lessons/{$lesson->id}/video")
        ->assertOk()
        ->assertJsonPath('data.is_embed', true)
        ->assertJsonPath('data.url', 'https://www.youtube.com/embed/abc123');
});

it('signs a recording url for the student', function () {
    $lesson = aVideoLesson($this->tenant);
    $recording = aStoredRecording($this->tenant);
    withinTenant($this->tenant, fn () => LessonVideo::query()->create([
        'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id, 'source' => 'recording',
        'recording_id' => $recording->id,
    ]));
    Sanctum::actingAs($this->student);

    $res = getJson("/api/v1/me/lessons/{$lesson->id}/video")
        ->assertOk()
        ->assertJsonPath('data.is_embed', false);

    expect($res->json('data.url'))->toContain('recordings/rec-1.mp4');
});

it('404s a missing or other-tenant video for the student', function () {
    $lesson = aVideoLesson($this->tenant);
    Sanctum::actingAs($this->student);
    getJson("/api/v1/me/lessons/{$lesson->id}/video")->assertNotFound();

    // Attach in this tenant, then try as an intruder.
    withinTenant($this->tenant, fn () => LessonVideo::query()->create([
        'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id, 'source' => 'url', 'url' => 'https://x/y',
    ]));
    $intruder = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'student']);
    Sanctum::actingAs($intruder);
    getJson("/api/v1/me/lessons/{$lesson->id}/video")->assertNotFound();
});
