<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    app()->instance(AiClient::class, new FakeAiClient);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function aNotesLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'notes', 'title' => 'Class']);
}

/* ---- Student surface ---- */

it('returns approved notes to the student and never the transcript', function () {
    $lesson = aNotesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->approved()->create([
        'lesson_id' => $lesson->id, 'notes' => '## Notes\n\nStudy this.', 'transcript' => 'SECRET RAW TRANSCRIPT',
    ]);
    Sanctum::actingAs($this->student);

    $res = getJson("/api/v1/me/lessons/{$lesson->id}/notes")
        ->assertOk()
        ->assertJsonPath('data.notes', '## Notes\n\nStudy this.');

    expect($res->getContent())->not->toContain('SECRET RAW TRANSCRIPT')
        ->and($res->getContent())->not->toContain('transcript');
});

it('hides draft notes from students', function () {
    $lesson = aNotesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->withNotes()->create(['lesson_id' => $lesson->id]); // draft
    Sanctum::actingAs($this->student);

    getJson("/api/v1/me/lessons/{$lesson->id}/notes")->assertNotFound();
});

it('rejects notes for guests and other tenants', function () {
    $lesson = aNotesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->approved()->create(['lesson_id' => $lesson->id]);

    getJson("/api/v1/me/lessons/{$lesson->id}/notes")->assertUnauthorized();

    $intruder = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'student']);
    Sanctum::actingAs($intruder);
    getJson("/api/v1/me/lessons/{$lesson->id}/notes")->assertNotFound();
});

/* ---- Admin surface ---- */

it('lets a curriculum manager save a transcript, upload, and approve', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aNotesLesson($this->tenant);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    putJson("/api/v1/admin/lessons/{$lesson->id}/notes", ['transcript' => 'A class about recursion and base cases.'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'draft');

    // Upload path.
    postJson("/api/v1/admin/lessons/{$lesson->id}/notes/upload", [
        'file' => UploadedFile::fake()->createWithContent('t.txt', 'Uploaded transcript about loops.'),
    ])->assertStatus(202);

    $note = LessonNote::withoutGlobalScopes()->where('lesson_id', $lesson->id)->first();
    $note->update(['notes' => '## Notes\n\nApproved.']);
    postJson("/api/v1/admin/lesson-notes/{$note->id}/approve", [])->assertOk()->assertJsonPath('data.status', 'approved');
});

it('denies notes management without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    $counselor = counselorIn($this->tenant);
    Sanctum::actingAs($counselor);

    getJson('/api/v1/admin/note-lessons')->assertForbidden();
});

it('does not approve another tenant\'s note', function () {
    $this->seed(RolePermissionSeeder::class);
    $other = Tenant::factory()->create();
    $lesson = aNotesLesson($other);
    $foreign = LessonNote::factory()->for($other)->withNotes()->create(['lesson_id' => $lesson->id]);

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson("/api/v1/admin/lesson-notes/{$foreign->id}/approve", [])->assertNotFound();
});
