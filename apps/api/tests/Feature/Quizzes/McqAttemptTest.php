<?php

declare(strict_types=1);

use App\Actions\Auth\ConsumeMagicLink;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\TopicCompletion;
use App\Models\User;
use App\Support\MagicLink\MagicLinkService;
use App\Support\Scoring\ScoreCalculator;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

/**
 * A quiz with two questions (correct indices 1 and 2) + a pending attempt for the student.
 *
 * @return array{attempt: QuizAttempt, q1: int, q2: int, module: Module}
 */
function attemptFixture(Tenant $tenant, User $student, bool $enroll = false): array
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id, 'position' => 1]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);
    $lesson = Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'quiz']);
    $quiz = Quiz::factory()->for($tenant)->approved()->create(['lesson_id' => $lesson->id, 'shuffle' => false, 'time_limit_sec' => 600]);
    $q1 = QuizQuestion::factory()->for($tenant)->create(['quiz_id' => $quiz->id, 'correct_index' => 1, 'position' => 0]);
    $q2 = QuizQuestion::factory()->for($tenant)->create(['quiz_id' => $quiz->id, 'correct_index' => 2, 'position' => 1]);

    if ($enroll) {
        $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id]);
        BatchMember::factory()->for($tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled']);
        // 1 of 2 topics complete → 50% topic mastery.
        TopicCompletion::create(['tenant_id' => $tenant->id, 'user_id' => $student->id, 'topic_id' => $topic->id, 'completed_at' => now()]);
        Topic::factory()->for($tenant)->create(['module_id' => $module->id]);
    }

    $attempt = QuizAttempt::factory()->for($tenant)->create(['quiz_id' => $quiz->id, 'user_id' => $student->id, 'status' => 'pending']);

    return ['attempt' => $attempt, 'q1' => $q1->id, 'q2' => $q2->id, 'module' => $module];
}

it('starts the timer and hides correct answers on open', function () {
    ['attempt' => $attempt] = attemptFixture($this->tenant, $this->student);
    Sanctum::actingAs($this->student);

    $res = getJson("/api/v1/me/mcq/{$attempt->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    expect($res->json('data.deadline_at'))->not->toBeNull()
        ->and($res->json('data.questions.0'))->not->toHaveKey('correct_index')
        ->and($attempt->fresh()->deadline_at)->not->toBeNull();
});

it('grades a submission, records integrity, and marks it submitted', function () {
    ['attempt' => $attempt, 'q1' => $q1, 'q2' => $q2] = attemptFixture($this->tenant, $this->student);
    Sanctum::actingAs($this->student);
    getJson("/api/v1/me/mcq/{$attempt->id}"); // start

    postJson("/api/v1/me/mcq/{$attempt->id}/submit", [
        'answers' => [(string) $q1 => 1, (string) $q2 => 0], // 1 of 2 correct
        'integrity' => ['tab_blurs' => 2, 'paste_count' => 1, 'duration_sec' => 90],
    ])
        ->assertOk()
        ->assertJsonPath('data.score_pct', 50)
        ->assertJsonPath('data.status', 'submitted');

    expect($attempt->fresh()->integrity['tab_blurs'])->toBe(2);
});

it('marks a late submission expired', function () {
    ['attempt' => $attempt, 'q1' => $q1, 'q2' => $q2] = attemptFixture($this->tenant, $this->student);
    Sanctum::actingAs($this->student);

    Carbon::setTestNow('2026-07-17 09:00:00');
    getJson("/api/v1/me/mcq/{$attempt->id}"); // deadline = 09:10

    Carbon::setTestNow('2026-07-17 09:20:00'); // 10 min late
    postJson("/api/v1/me/mcq/{$attempt->id}/submit", ['answers' => [(string) $q1 => 1, (string) $q2 => 2]])
        ->assertOk()
        ->assertJsonPath('data.status', 'expired');

    Carbon::setTestNow();
});

it('blends a submitted quiz score into module mastery', function () {
    ['attempt' => $attempt, 'q1' => $q1, 'q2' => $q2] = attemptFixture($this->tenant, $this->student, enroll: true);
    Sanctum::actingAs($this->student);
    getJson("/api/v1/me/mcq/{$attempt->id}");
    postJson("/api/v1/me/mcq/{$attempt->id}/submit", ['answers' => [(string) $q1 => 1, (string) $q2 => 2]]); // 100%

    $scores = app(ScoreCalculator::class)->computeFor($this->student->fresh());
    // topic 50% * 0.6 + quiz 100% * 0.4 = 70
    expect(collect($scores['mastery'])->firstWhere('module_id', $attempt->quiz->lesson->topic->module_id)['pct'])->toBe(70);
});

it('leaves mastery unchanged when there is no submitted attempt (P3.2 determinism)', function () {
    attemptFixture($this->tenant, $this->student, enroll: true); // attempt stays pending

    $scores = app(ScoreCalculator::class)->computeFor($this->student->fresh());
    // Pure topic completion: 1 of 2 topics = 50%.
    expect(collect($scores['mastery'])->first()['pct'])->toBe(50);
});

it('rejects the mcq for guests and other tenants', function () {
    ['attempt' => $attempt] = attemptFixture($this->tenant, $this->student);

    getJson("/api/v1/me/mcq/{$attempt->id}")->assertUnauthorized();

    $intruder = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'student']);
    Sanctum::actingAs($intruder);
    getJson("/api/v1/me/mcq/{$attempt->id}")->assertNotFound();
});

it('lands the student on their attempt via the magic link', function () {
    ['attempt' => $attempt] = attemptFixture($this->tenant, $this->student);

    $url = app(MagicLinkService::class)->create(
        $this->tenant, 'mcq.attempt', $this->student,
        ['attempt_id' => $attempt->id, 'redirect' => '/mcq/'.$attempt->id],
    );
    $token = (string) str($url)->after('/magic/')->before('?');

    $link = app(ConsumeMagicLink::class)->handle($token);

    expect($link->user_id)->toBe($this->student->id)
        ->and($link->payload['redirect'])->toBe('/mcq/'.$attempt->id);
});
