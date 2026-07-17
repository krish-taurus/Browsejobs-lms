<?php

declare(strict_types=1);

use App\Actions\Quizzes\ApproveQuiz;
use App\Actions\Quizzes\GenerateQuiz;
use App\Enums\QuizStatus;
use App\Models\AiEvent;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
});

/** A `quiz` lesson under a real module in the tenant. */
function quizLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create(['description' => 'Intro to Python.']);
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'quiz']);
}

it('enforces one quiz per lesson', function () {
    $lesson = quizLesson($this->tenant);
    Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);

    expect(fn () => Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]))
        ->toThrow(QueryException::class);
});

it('generates draft questions from the AI response, dropping malformed items', function () {
    $lesson = quizLesson($this->tenant);
    $quiz = Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);
    $staff = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);

    $this->fake->reply = 'Here you go: '.json_encode([
        ['prompt' => 'What is 2+2?', 'options' => ['3', '4', '5', '6'], 'correct_index' => 1, 'explanation' => '2+2=4'],
        ['prompt' => 'bad', 'options' => ['only one'], 'correct_index' => 0], // dropped (too few options)
    ]);

    $count = app(GenerateQuiz::class)->handle($quiz, $staff, 8);

    expect($count)->toBe(1)
        ->and($quiz->fresh()->source->value)->toBe('ai')
        ->and($quiz->fresh()->status)->toBe(QuizStatus::Draft);

    // Billed to the staff user, never a student.
    expect(AiEvent::withoutGlobalScopes()->where('user_id', $staff->id)->where('purpose', 'quiz_gen')->exists())->toBeTrue();
});

it('leaves the quiz a draft when the AI output is unparseable', function () {
    $lesson = quizLesson($this->tenant);
    $quiz = Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);
    $staff = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->fake->reply = 'Sorry, I cannot help with that.';

    $count = app(GenerateQuiz::class)->handle($quiz, $staff, 8);

    expect($count)->toBe(0)->and($quiz->fresh()->questions()->count())->toBe(0);
});

it('approves a quiz with questions and audits it, idempotently', function () {
    $lesson = quizLesson($this->tenant);
    $quiz = Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);
    QuizQuestion::factory()->for($this->tenant)->create(['quiz_id' => $quiz->id]);
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);

    app(ApproveQuiz::class)->handle($quiz, $trainer);
    app(ApproveQuiz::class)->handle($quiz->fresh(), $trainer); // no-op

    expect($quiz->fresh()->status)->toBe(QuizStatus::Approved)
        ->and($quiz->fresh()->isDispatchable())->toBeTrue()
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'quiz.approved')->count())->toBe(1);
});

it('refuses to approve a quiz with no questions', function () {
    $lesson = quizLesson($this->tenant);
    $quiz = Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);

    expect(fn () => app(ApproveQuiz::class)->handle($quiz, null))
        ->toThrow(ValidationException::class);
});

/* ---- Admin HTTP gate + cross-tenant ---- */

it('lets a curriculum manager approve a quiz over http', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = quizLesson($this->tenant);
    $quiz = Quiz::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);
    QuizQuestion::factory()->for($this->tenant)->create(['quiz_id' => $quiz->id]);

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson("/api/v1/admin/quizzes/{$quiz->id}/approve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('denies quiz management without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    $counselor = counselorIn($this->tenant);
    Sanctum::actingAs($counselor);

    getJson('/api/v1/admin/quiz-lessons')->assertForbidden();
});

it('does not approve another tenant\'s quiz', function () {
    $this->seed(RolePermissionSeeder::class);
    $other = Tenant::factory()->create();
    $lesson = quizLesson($other);
    $foreignQuiz = Quiz::factory()->for($other)->create(['lesson_id' => $lesson->id]);
    QuizQuestion::factory()->for($other)->create(['quiz_id' => $foreignQuiz->id]);

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson("/api/v1/admin/quizzes/{$foreignQuiz->id}/approve", [])->assertNotFound();
});
