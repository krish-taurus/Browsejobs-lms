<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Storage::fake('s3');
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
});

function pdfNotesLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'notes']);
}

it('renders approved notes into a branded PDF stored on s3', function () {
    Sanctum::actingAs($this->admin);
    $lesson = pdfNotesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->create([
        'lesson_id' => $lesson->id,
        'notes' => "# ETL Basics\n\nExtract, transform, load.\n\n- point one\n- point two",
        'status' => 'approved',
    ]);

    postJson("/api/v1/admin/lessons/{$lesson->id}/notes/pdf")
        ->assertOk()
        ->assertJsonPath('data.url', fn ($u) => is_string($u) && str_contains($u, "lesson-{$lesson->id}.pdf"));

    Storage::disk('s3')->assertExists("notes/{$this->tenant->id}/lesson-{$lesson->id}.pdf");
    $bytes = Storage::disk('s3')->get("notes/{$this->tenant->id}/lesson-{$lesson->id}.pdf");
    expect(substr($bytes, 0, 4))->toBe('%PDF'); // it's a real PDF
});

it('refuses to make a PDF with no notes', function () {
    Sanctum::actingAs($this->admin);
    $lesson = pdfNotesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id, 'notes' => null]);

    postJson("/api/v1/admin/lessons/{$lesson->id}/notes/pdf")->assertStatus(422);
});

it('accepts an uploaded PDF as the downloadable file', function () {
    Sanctum::actingAs($this->admin);
    $lesson = pdfNotesLesson($this->tenant);

    postJson("/api/v1/admin/lessons/{$lesson->id}/notes/pdf/upload", [
        'file' => UploadedFile::fake()->create('handout.pdf', 40, 'application/pdf'),
    ])->assertOk()->assertJsonPath('data.url', fn ($u) => is_string($u));

    $note = LessonNote::query()->where('lesson_id', $lesson->id)->firstOrFail();
    expect($note->pdf_uploaded)->toBeTrue();
    Storage::disk('s3')->assertExists($note->pdf_path);
});

it('rejects a non-PDF upload', function () {
    Sanctum::actingAs($this->admin);
    $lesson = pdfNotesLesson($this->tenant);

    postJson("/api/v1/admin/lessons/{$lesson->id}/notes/pdf/upload", [
        'file' => UploadedFile::fake()->create('notes.docx', 40),
    ])->assertStatus(422);
});

it('gives an enrolled student a download URL for approved notes with a PDF', function () {
    $lesson = pdfNotesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->create([
        'lesson_id' => $lesson->id, 'notes' => '# Hi', 'status' => 'approved',
        'pdf_path' => "notes/{$this->tenant->id}/lesson-{$lesson->id}.pdf",
    ]);
    Storage::disk('s3')->put("notes/{$this->tenant->id}/lesson-{$lesson->id}.pdf", '%PDF-fake');

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    getJson("/api/v1/me/lessons/{$lesson->id}/notes")
        ->assertOk()
        ->assertJsonPath('data.pdf_url', fn ($u) => is_string($u) && str_contains($u, '.pdf'));
});

it('denies PDF generation without manage-curriculum', function () {
    $lesson = pdfNotesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id, 'notes' => '# x', 'status' => 'approved']);
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    postJson("/api/v1/admin/lessons/{$lesson->id}/notes/pdf")->assertForbidden();
});

it('cannot generate a PDF for another tenant lesson (cross-tenant denial)', function () {
    Sanctum::actingAs($this->admin);
    $foreign = pdfNotesLesson(Tenant::factory()->create());
    LessonNote::factory()->for($foreign->tenant)->create(['lesson_id' => $foreign->id, 'notes' => '# x', 'status' => 'approved']);

    postJson("/api/v1/admin/lessons/{$foreign->id}/notes/pdf")->assertNotFound();
});
