<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function aFlashcardLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'notes', 'title' => 'Recursion']);
}

function curriculumAdmin(Tenant $tenant): User
{
    $admin = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');

    return $admin;
}

/* ---- Admin ---- */

it('AI-drafts flashcards without persisting them', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aFlashcardLesson($this->tenant);
    Sanctum::actingAs(curriculumAdmin($this->tenant));
    $this->fake->reply = 'Here: '.json_encode([
        ['front' => 'What is a base case?', 'back' => 'The condition that stops recursion.'],
        ['front' => 'Recursion needs?', 'back' => 'A base case and a recursive step.'],
    ]);

    postJson("/api/v1/admin/lessons/{$lesson->id}/flashcards/generate", ['count' => 2])
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.front', 'What is a base case?');

    expect(Flashcard::withoutGlobalScopes()->where('lesson_id', $lesson->id)->count())->toBe(0); // draft only
});

it('saves (publishes) a set of flashcards, replacing any prior set', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aFlashcardLesson($this->tenant);
    Sanctum::actingAs(curriculumAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/flashcards", ['cards' => [
        ['front' => 'A', 'back' => 'a'], ['front' => 'B', 'back' => 'b'],
    ]])->assertOk()->assertJsonCount(2, 'data');

    // Re-save replaces the set.
    putJson("/api/v1/admin/lessons/{$lesson->id}/flashcards", ['cards' => [['front' => 'C', 'back' => 'c']]])
        ->assertOk()->assertJsonCount(1, 'data');

    expect(Flashcard::withoutGlobalScopes()->where('lesson_id', $lesson->id)->count())->toBe(1);
});

it('denies flashcard authoring without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aFlashcardLesson($this->tenant);
    Sanctum::actingAs(counselorIn($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/flashcards", ['cards' => []])->assertForbidden();
});

/* ---- Student review + SM-2 ---- */

it('returns the deck with new cards due first and schedules a review', function () {
    $lesson = aFlashcardLesson($this->tenant);
    $cards = withinTenant($this->tenant, fn () => collect(['one', 'two'])->map(fn ($f, $i) => Flashcard::query()->create([
        'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id, 'front' => $f, 'back' => 'x', 'position' => $i,
    ])));
    Sanctum::actingAs($this->student);

    // New cards are all due.
    getJson("/api/v1/me/lessons/{$lesson->id}/flashcards")
        ->assertOk()
        ->assertJsonPath('meta.due_count', 2)
        ->assertJsonPath('data.0.due', true);

    // Review one card "good" → it gets a future due date and drops out of "due".
    postJson("/api/v1/me/flashcards/{$cards[0]->id}/review", ['rating' => 'good'])
        ->assertOk()
        ->assertJsonPath('data.interval_days', 1);

    expect(FlashcardReview::withoutGlobalScopes()->where('user_id', $this->student->id)->where('flashcard_id', $cards[0]->id)->first()->due_at)
        ->not->toBeNull();

    getJson("/api/v1/me/lessons/{$lesson->id}/flashcards")->assertJsonPath('meta.due_count', 1);
});

it('rejects an unknown rating', function () {
    $lesson = aFlashcardLesson($this->tenant);
    $card = withinTenant($this->tenant, fn () => Flashcard::query()->create([
        'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id, 'front' => 'q', 'back' => 'a',
    ]));
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/flashcards/{$card->id}/review", ['rating' => 'brilliant'])->assertStatus(422);
});

it('will not let a student review another tenant\'s card', function () {
    $other = Tenant::factory()->create();
    $lesson = aFlashcardLesson($other);
    $foreign = withinTenant($other, fn () => Flashcard::query()->create([
        'tenant_id' => $other->id, 'lesson_id' => $lesson->id, 'front' => 'q', 'back' => 'a',
    ]));
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/flashcards/{$foreign->id}/review", ['rating' => 'good'])->assertNotFound();
});
