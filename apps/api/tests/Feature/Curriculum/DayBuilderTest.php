<?php

declare(strict_types=1);

use App\Models\AiEvent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Day-by-day syllabus builder (ADR 0049): manual day entry, AI day plans from
 * keywords, ELI5 notes drafts, and MCQ assignment papers — all AI output stays
 * draft behind the existing approve flows.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin);
});

function dayModule(Tenant $tenant): Module
{
    $course = Course::factory()->for($tenant)->create(['name' => 'Data Engineering', 'description' => 'Pipelines.']);

    return Module::factory()->for($tenant)->create(['course_id' => $course->id, 'name' => 'Python']);
}

it('creates a day manually with keywords and a summary', function () {
    $module = dayModule($this->tenant);

    postJson("/api/v1/admin/modules/{$module->id}/days", [
        'name' => 'Variables and data types',
        'day_number' => 1,
        'summary' => 'What a variable is, and the basic types.',
        'keywords' => ['variables', 'int', 'string'],
    ])->assertCreated();

    $topic = Topic::withoutGlobalScopes()->sole();
    expect($topic->day_number)->toBe(1)
        ->and($topic->keywords)->toBe(['variables', 'int', 'string'])
        ->and($topic->summary)->toContain('variable');

    // The same day number cannot be used twice within a module.
    postJson("/api/v1/admin/modules/{$module->id}/days", [
        'name' => 'Duplicate day', 'day_number' => 1,
    ])->assertUnprocessable();
});

it('drafts a day plan from keywords without persisting anything', function () {
    $module = dayModule($this->tenant);
    $this->fake->reply = json_encode([
        'name' => 'Loops and iteration',
        'summary' => 'Repeat work with for and while loops.',
        'subtopics' => ['for loops', 'while loops', 'break and continue'],
        'keywords' => ['loops', 'iteration'],
    ]);

    postJson("/api/v1/admin/modules/{$module->id}/days/plan", [
        'keywords' => ['loops', 'for', 'while'],
        'day_number' => 2,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Loops and iteration')
        ->assertJsonCount(3, 'data.subtopics');

    expect(Topic::withoutGlobalScopes()->count())->toBe(0)
        ->and(AiEvent::withoutGlobalScopes()->where('purpose', 'day_plan')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('rejects an unparseable day-plan response', function () {
    $module = dayModule($this->tenant);
    $this->fake->reply = 'I cannot help with that.';

    postJson("/api/v1/admin/modules/{$module->id}/days/plan", [
        'keywords' => ['loops'], 'day_number' => 2,
    ])->assertUnprocessable()->assertJsonPath('error.code', 'day_plan_failed');
});

it('generates beginner notes as a draft on an auto-created notes lesson', function () {
    $module = dayModule($this->tenant);
    $topic = Topic::factory()->for($this->tenant)->create([
        'module_id' => $module->id, 'name' => 'Functions', 'day_number' => 3,
        'keywords' => ['def', 'arguments', 'return'], 'summary' => 'Reusable blocks of code.',
    ]);
    $this->fake->reply = "## What you will learn today\nA function is like a recipe.";

    postJson("/api/v1/admin/topics/{$topic->id}/simple-notes")->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    $lesson = Lesson::withoutGlobalScopes()->where('topic_id', $topic->id)->where('type', 'notes')->sole();
    $note = LessonNote::withoutGlobalScopes()->where('lesson_id', $lesson->id)->sole();
    expect($note->notes)->toContain('recipe')
        ->and($note->status->value)->toBe('draft')
        ->and($note->transcript)->toContain('Functions');

    // Regenerating reuses the same lesson instead of stacking new ones.
    postJson("/api/v1/admin/topics/{$topic->id}/simple-notes")->assertCreated();
    expect(Lesson::withoutGlobalScopes()->where('topic_id', $topic->id)->where('type', 'notes')->count())->toBe(1);
});

it('refuses to generate an assignment before the chapter has notes', function () {
    $module = dayModule($this->tenant);
    $topic = Topic::factory()->for($this->tenant)->create([
        'module_id' => $module->id, 'name' => 'Lists', 'day_number' => 4,
    ]);

    postJson("/api/v1/admin/topics/{$topic->id}/assignment/generate")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'notes_required');

    expect(Quiz::withoutGlobalScopes()->count())->toBe(0);
});

it('generates numbered assignments from the chapter notes', function () {
    $module = dayModule($this->tenant);
    $topic = Topic::factory()->for($this->tenant)->create([
        'module_id' => $module->id, 'name' => 'Lists', 'day_number' => 4,
    ]);
    $lesson = Lesson::factory()->for($this->tenant)->create(['topic_id' => $topic->id, 'type' => 'notes']);
    LessonNote::factory()->for($this->tenant)->create([
        'lesson_id' => $lesson->id, 'notes' => 'A list is like a train: wagons in order. mylist[0] is the first wagon.',
    ]);
    $this->fake->reply = json_encode([
        ['prompt' => 'What does mylist[0] return?', 'options' => ['First item', 'Last item', 'An error', 'The length'], 'correct_index' => 0, 'explanation' => 'Indexing starts at 0.'],
        ['prompt' => 'Which adds an item to the end?', 'options' => ['append', 'pop', 'sort', 'index'], 'correct_index' => 0, 'explanation' => 'append() adds to the end.'],
    ]);

    postJson("/api/v1/admin/topics/{$topic->id}/assignment/generate")->assertCreated()
        ->assertJsonPath('data.questions', 2)
        ->assertJsonPath('data.status', 'draft');

    // Questions come FROM the notes, and each generation adds a NEW numbered assignment.
    expect($this->fake->calls[0]->user)->toContain('wagons in order');
    postJson("/api/v1/admin/topics/{$topic->id}/assignment/generate")->assertCreated();

    $quizzes = Quiz::withoutGlobalScopes()->orderBy('id')->get();
    expect($quizzes)->toHaveCount(2)
        ->and($quizzes[0]->title)->toBe('Lists — Assignment 1')
        ->and($quizzes[1]->title)->toBe('Lists — Assignment 2')
        ->and($quizzes[0]->lesson_id)->not->toBe($quizzes[1]->lesson_id);
});

it('renders, uploads, and serves the assignment paper PDF', function () {
    Storage::fake('s3');
    $module = dayModule($this->tenant);
    $topic = Topic::factory()->for($this->tenant)->create(['module_id' => $module->id, 'name' => 'Dicts', 'day_number' => 5]);
    $lesson = Lesson::factory()->for($this->tenant)->create(['topic_id' => $topic->id, 'type' => 'quiz']);
    $quiz = Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id, 'title' => 'Dicts — assignment']);
    $quiz->questions()->create([
        'tenant_id' => $this->tenant->id, 'prompt' => 'What is a dict?',
        'options' => ['Key-value store', 'A list', 'A loop', 'A file'], 'correct_index' => 0,
        'explanation' => 'Dicts map keys to values.', 'position' => 0,
    ]);

    postJson("/api/v1/admin/quizzes/{$quiz->id}/pdf")->assertOk();
    Storage::disk('s3')->assertExists("assignments/{$this->tenant->id}/lesson-{$lesson->id}.pdf");
    expect($quiz->refresh()->pdf_uploaded)->toBeFalse();

    // A mentor can attach their own paper instead.
    postJson("/api/v1/admin/quizzes/{$quiz->id}/pdf/upload", [
        'file' => UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf'),
    ])->assertOk()->assertJsonPath('data.uploaded', true);
    expect($quiz->refresh()->pdf_uploaded)->toBeTrue();

    getJson("/api/v1/admin/quizzes/{$quiz->id}/pdf")->assertOk()->assertJsonStructure(['data' => ['url']]);
});

it('denies the day builder to staff without manage-curriculum and across tenants', function () {
    $module = dayModule($this->tenant);

    // Staff without the permission.
    $support = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $support->assignRole('support-agent');
    Sanctum::actingAs($support);
    getJson("/api/v1/admin/modules/{$module->id}/days")->assertForbidden();

    // Admin of another tenant cannot touch this module or its topics.
    $otherTenant = Tenant::factory()->create();
    $outsider = User::factory()->for($otherTenant)->create(['user_type' => 'staff']);
    $outsider->assignRole('admin');
    Sanctum::actingAs($outsider);
    getJson("/api/v1/admin/modules/{$module->id}/days")->assertNotFound();
    postJson("/api/v1/admin/modules/{$module->id}/days", ['name' => 'X', 'day_number' => 9])->assertNotFound();
});

it('lists days with their notes and assignment status', function () {
    $module = dayModule($this->tenant);
    $topic = Topic::factory()->for($this->tenant)->create([
        'module_id' => $module->id, 'name' => 'Sets', 'day_number' => 6, 'keywords' => ['sets'],
    ]);
    $lesson = Lesson::factory()->for($this->tenant)->create(['topic_id' => $topic->id, 'type' => 'notes']);
    LessonNote::factory()->for($this->tenant)->create([
        'lesson_id' => $lesson->id, 'notes' => 'Sets are bags without duplicates.', 'status' => 'approved',
    ]);

    getJson("/api/v1/admin/modules/{$module->id}/days")->assertOk()
        ->assertJsonPath('data.module.name', 'Python')
        ->assertJsonPath('data.days.0.day_number', 6)
        ->assertJsonPath('data.days.0.notes.status', 'approved')
        ->assertJsonPath('data.days.0.assignments', []);
});
