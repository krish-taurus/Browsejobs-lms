<?php

declare(strict_types=1);

use App\Models\AiEvent;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin);
});

function assignmentLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create(['description' => 'Intro to Python.']);
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'assignment']);
}

it('generates an assignment draft from the AI response without persisting it', function () {
    $lesson = assignmentLesson($this->tenant);
    $this->fake->reply = 'Here you go: '.json_encode([
        'title' => 'Build an ETL pipeline',
        'instructions' => "- Ingest the CSV\n- Transform\n- Load to warehouse\n- Submit your repo link",
        'rubric' => [
            ['criterion' => 'Correctness', 'max_points' => 50],
            ['criterion' => 'Code quality', 'max_points' => 30],
            ['criterion' => 'Docs', 'max_points' => 20],
        ],
    ]);

    postJson("/api/v1/admin/lessons/{$lesson->id}/assignment/generate")
        ->assertOk()
        ->assertJsonPath('data.title', 'Build an ETL pipeline')
        ->assertJsonPath('data.max_points', 100)
        ->assertJsonCount(3, 'data.rubric');

    // Draft only — nothing persisted until the trainer saves.
    expect(Assignment::query()->where('lesson_id', $lesson->id)->exists())->toBeFalse();
    // Billed to the staff actor.
    expect(AiEvent::withoutGlobalScopes()->where('user_id', $this->admin->id)->where('purpose', 'assignment_gen')->exists())->toBeTrue();
});

it('falls back to a single rubric row when the model omits one', function () {
    $lesson = assignmentLesson($this->tenant);
    $this->fake->reply = json_encode(['title' => 'Task', 'instructions' => 'Do the thing.']);

    postJson("/api/v1/admin/lessons/{$lesson->id}/assignment/generate")
        ->assertOk()
        ->assertJsonPath('data.max_points', 100)
        ->assertJsonCount(1, 'data.rubric');
});

it('422s on unparseable AI output', function () {
    $lesson = assignmentLesson($this->tenant);
    $this->fake->reply = 'Sorry, I cannot help with that.';

    postJson("/api/v1/admin/lessons/{$lesson->id}/assignment/generate")
        ->assertStatus(422)->assertJsonPath('error.code', 'generation_failed');
});

it('refuses to generate for a non-assignment lesson', function () {
    $course = Course::factory()->for($this->tenant)->create();
    $module = Module::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($this->tenant)->create(['module_id' => $module->id]);
    $notes = Lesson::factory()->for($this->tenant)->create(['topic_id' => $topic->id, 'type' => 'notes']);

    postJson("/api/v1/admin/lessons/{$notes->id}/assignment/generate")->assertStatus(422);
});

it('denies staff without manage-curriculum', function () {
    $lesson = assignmentLesson($this->tenant);
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    postJson("/api/v1/admin/lessons/{$lesson->id}/assignment/generate")->assertForbidden();
});

it('cannot generate for another tenant lesson (cross-tenant denial)', function () {
    $foreign = assignmentLesson(Tenant::factory()->create());

    postJson("/api/v1/admin/lessons/{$foreign->id}/assignment/generate")->assertNotFound();
});
