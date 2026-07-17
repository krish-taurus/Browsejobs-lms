<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Events\TopicCompleted;
use App\Models\ActivityEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\MagicLink;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Module;
use App\Models\MonetizationSetting;
use App\Models\PointsEvent;
use App\Models\StudentBadge;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Scoring\ScoreCalculator;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['monetization.text_practice_enabled' => true]);
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
});

/**
 * An enrolled student on a course that has an active mock blueprint.
 *
 * @return array{student: User, course: Course, blueprint: MockBlueprint}
 */
function mockReadyStudent(Tenant $tenant): array
{
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value]);
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id,
        'status' => BatchMemberStatus::Enrolled->value,
    ]);
    $blueprint = MockBlueprint::factory()->for($tenant)->create([
        'course_id' => $course->id,
        'role_title' => 'Data Engineer',
        'competencies' => ['SQL', 'Python', 'Communication'],
        'opening_question' => 'Walk me through a pipeline you have built.',
    ]);

    return compact('student', 'course', 'blueprint');
}

/** A syntactically valid scorecard reply for the fake AI client. */
function scorecardJson(int $overall = 82): string
{
    return json_encode([
        'overall' => $overall,
        'competencies' => [['name' => 'SQL', 'score' => $overall]],
        'strong_moments' => ['Clear pipeline walkthrough'],
        'weak_moments' => ['Vague on partitioning'],
        'model_answers' => [['question' => 'Q', 'answer' => 'A']],
        'actions' => ['Redo the SQL lab', 'Practice out loud', 'Review partitioning'],
    ], JSON_THROW_ON_ERROR);
}

/** Start a session and answer enough times (via HTTP) to be finishable. */
function startedMock(User $student, int $answers = 2): int
{
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mocks')->assertCreated()->json('data.id');

    for ($i = 0; $i < $answers; $i++) {
        postJson("/api/v1/me/mocks/{$id}/answer", ['answer' => "Detailed answer number {$i} about pipelines."])
            ->assertOk();
    }

    return (int) $id;
}

/* ---- Start ---- */

it('rejects starting a mock when text practice is disabled', function () {
    config(['monetization.text_practice_enabled' => false]);
    ['student' => $student] = mockReadyStudent($this->tenant);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/mocks')->assertForbidden();
});

it('starts a mock with the deterministic opening question and resumes it', function () {
    ['student' => $student] = mockReadyStudent($this->tenant);
    Sanctum::actingAs($student);

    $first = postJson('/api/v1/me/mocks')
        ->assertCreated()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.role_title', 'Data Engineer')
        ->assertJsonPath('data.turns.0.role', 'interviewer')
        ->assertJsonPath('data.turns.0.body', 'Walk me through a pipeline you have built.');

    // Starting costs zero AI tokens.
    expect($this->fake->calls)->toBeEmpty();

    // A second start resumes the same session instead of forking.
    postJson('/api/v1/me/mocks')->assertCreated()
        ->assertJsonPath('data.id', $first->json('data.id'));
    expect(MockInterview::withoutGlobalScopes()->count())->toBe(1);
});

it('returns 422 when no blueprint covers the student\'s course', function () {
    ['student' => $student, 'blueprint' => $blueprint] = mockReadyStudent($this->tenant);
    withinTenant($this->tenant, fn () => $blueprint->update(['is_active' => false]));
    Sanctum::actingAs($student);

    postJson('/api/v1/me/mocks')->assertUnprocessable();
});

/* ---- Answer flow ---- */

it('asks an adaptive follow-up per answer and stops at the question cap', function () {
    config(['mocks.max_questions' => 3]);
    ['student' => $student] = mockReadyStudent($this->tenant);
    $this->fake->replies = ['How do you handle late-arriving data?', 'What is a slowly changing dimension?'];
    Sanctum::actingAs($student);

    $id = postJson('/api/v1/me/mocks')->json('data.id');

    postJson("/api/v1/me/mocks/{$id}/answer", ['answer' => 'I built a batch pipeline with Airflow.'])
        ->assertOk()
        ->assertJsonPath('data.turns.2.role', 'interviewer')
        ->assertJsonPath('data.turns.2.body', 'How do you handle late-arriving data?')
        ->assertJsonPath('data.ready_to_finish', false);

    // The interviewer prompt carries the transcript so questioning is adaptive.
    expect($this->fake->calls[0]->user)
        ->toContain('Airflow')
        ->toContain('Data Engineer');

    postJson("/api/v1/me/mocks/{$id}/answer", ['answer' => 'I use watermarking and reprocessing windows.'])
        ->assertOk()
        ->assertJsonPath('data.questions_asked', 3)
        ->assertJsonPath('data.ready_to_finish', true);

    // Cap reached: another answer is stored but no further AI question is asked.
    postJson("/api/v1/me/mocks/{$id}/answer", ['answer' => 'A final extra thought.'])->assertOk();
    expect(count($this->fake->calls))->toBe(2);
});

it('rejects answering someone else\'s or a cross-tenant mock', function () {
    ['student' => $student] = mockReadyStudent($this->tenant);
    $id = startedMock($student, answers: 0);

    $other = Tenant::factory()->create();
    $intruder = User::factory()->for($other)->create(['user_type' => 'student']);
    Sanctum::actingAs($intruder);

    postJson("/api/v1/me/mocks/{$id}/answer", ['answer' => 'Hijack attempt answer.'])->assertNotFound();
    getJson("/api/v1/me/mocks/{$id}")->assertNotFound();
});

/* ---- Finish + scorecard ---- */

it('refuses to finish before the minimum number of answers', function () {
    ['student' => $student] = mockReadyStudent($this->tenant);
    $id = startedMock($student, answers: 1);

    postJson("/api/v1/me/mocks/{$id}/finish")->assertUnprocessable();
});

it('finishes with an AI scorecard, awards points and the badge once, and logs telemetry', function () {
    ['student' => $student] = mockReadyStudent($this->tenant);
    $this->fake->replies = ['Q2?', 'Q3?', scorecardJson(82)];
    $id = startedMock($student);

    postJson("/api/v1/me/mocks/{$id}/finish")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.overall_score', 82)
        ->assertJsonPath('data.scorecard_source', 'ai')
        ->assertJsonPath('data.scorecard.actions.0', 'Redo the SQL lab');

    expect(PointsEvent::withoutGlobalScopes()->where('source', 'mock')->where('source_key', "mock:{$id}")->count())->toBe(1)
        ->and(StudentBadge::withoutGlobalScopes()->where('badge', 'first-mock-done')->count())->toBe(1)
        ->and(ActivityEvent::withoutGlobalScopes()->where('type', 'mock_completed')->where('value', 82)->count())->toBe(1);

    // Finishing again is a no-op: same card, no double points.
    postJson("/api/v1/me/mocks/{$id}/finish")->assertOk()->assertJsonPath('data.overall_score', 82);
    expect(PointsEvent::withoutGlobalScopes()->where('source', 'mock')->count())->toBe(1);
});

it('falls back to a conservative deterministic scorecard when grading fails', function () {
    ['student' => $student] = mockReadyStudent($this->tenant);
    $this->fake->replies = ['Q2?', 'Q3?', 'Sorry, I cannot produce JSON today.'];
    $id = startedMock($student);

    postJson("/api/v1/me/mocks/{$id}/finish")
        ->assertOk()
        ->assertJsonPath('data.scorecard_source', 'fallback')
        ->assertJsonPath('data.overall_score', 40);
});

it('falls back to the deterministic scorecard when the AI budget is exhausted', function () {
    ['student' => $student] = mockReadyStudent($this->tenant);
    $this->fake->replies = ['Q2?', 'Q3?', scorecardJson()];
    $id = startedMock($student);

    config(['ai.daily_token_budget' => 1]); // grading call would exceed budget
    postJson("/api/v1/me/mocks/{$id}/finish")
        ->assertOk()
        ->assertJsonPath('data.scorecard_source', 'fallback');
});

/* ---- Summary, PRI blend, human gate ---- */

it('summarises mocks and unlocks the human gate at the threshold', function () {
    config(['mocks.human_gate_score' => 70]);
    ['student' => $student] = mockReadyStudent($this->tenant);
    $this->fake->replies = ['Q2?', 'Q3?', scorecardJson(74)];
    $id = startedMock($student);
    postJson("/api/v1/me/mocks/{$id}/finish")->assertOk();

    getJson('/api/v1/me/mocks')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.best_score', 74)
        ->assertJsonPath('data.human_mock_unlocked', true)
        ->assertJsonPath('data.mocks.0.overall_score', 74);
});

it('blends the best mock score into PRI only when a completed mock exists', function () {
    ['student' => $student] = mockReadyStudent($this->tenant);

    // No completed mock → the blend config has zero effect.
    config(['scoring.pri.mock_blend' => 0.2]);
    $noMock = app(ScoreCalculator::class)->computeFor($student)['pri'];
    config(['scoring.pri.mock_blend' => 0.0]);
    expect(app(ScoreCalculator::class)->computeFor($student)['pri'])->toBe($noMock);

    $this->fake->replies = ['Q2?', 'Q3?', scorecardJson(100)];
    $id = startedMock($student);
    postJson("/api/v1/me/mocks/{$id}/finish")->assertOk();

    // Finishing the mock also writes telemetry, so re-baseline at blend 0
    // before isolating what the blend itself contributes.
    $base = app(ScoreCalculator::class)->computeFor($student)['pri'];
    config(['scoring.pri.mock_blend' => 0.2]);
    $blended = app(ScoreCalculator::class)->computeFor($student)['pri'];

    expect($blended)->toBe((int) round(0.8 * $base + 0.2 * 100))
        ->and($blended)->toBeGreaterThan($base);
});

/* ---- Nudge listener ---- */

it('nudges into a mock when a mock-enabled topic completes, and stays silent otherwise', function () {
    ['student' => $student, 'course' => $course] = mockReadyStudent($this->tenant);
    MessageTemplate::factory()->for($this->tenant)->create([
        'key' => 'mock_nudge', 'channel' => 'whatsapp',
        'body' => 'Nice work {{name}} — {{topic}} done. Practice: {{link}}',
    ]);
    [$onTopic, $offTopic] = withinTenant($this->tenant, function () use ($course) {
        $module = Module::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);

        return [
            Topic::factory()->create(['tenant_id' => $this->tenant->id, 'module_id' => $module->id, 'mock_enabled' => true]),
            Topic::factory()->create(['tenant_id' => $this->tenant->id, 'module_id' => $module->id, 'mock_enabled' => false]),
        ];
    });

    TopicCompleted::dispatch($student, $onTopic);
    TopicCompleted::dispatch($student, $offTopic);

    expect(Message::withoutGlobalScopes()->where('template_key', 'mock_nudge')->count())->toBe(1)
        ->and(MagicLink::withoutGlobalScopes()->where('action', 'mock.start')->count())->toBe(1);

    // Flag off → no nudge even for a mock-enabled topic.
    config(['monetization.text_practice_enabled' => false]);
    withinTenant($this->tenant, fn () => MonetizationSetting::query()->delete());
    TopicCompleted::dispatch($student, $onTopic);
    expect(Message::withoutGlobalScopes()->where('template_key', 'mock_nudge')->count())->toBe(1);
});

/* ---- Admin ---- */

it('lets a curriculum manager CRUD blueprints and see recent mocks', function () {
    $this->seed(RolePermissionSeeder::class);
    $course = Course::factory()->for($this->tenant)->create();
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    $created = postJson('/api/v1/admin/mock-blueprints', [
        'course_id' => $course->id,
        'role_title' => 'QA Engineer',
        'competencies' => ['Testing', 'Automation'],
        'opening_question' => 'How would you test a login form?',
    ])->assertCreated()->assertJsonPath('data.role_title', 'QA Engineer');

    $id = $created->json('data.id');

    patchJson("/api/v1/admin/mock-blueprints/{$id}", ['role_title' => 'Senior QA Engineer'])
        ->assertOk()
        ->assertJsonPath('data.role_title', 'Senior QA Engineer');

    getJson('/api/v1/admin/mock-blueprints')
        ->assertOk()
        ->assertJsonPath('data.blueprints.0.role_title', 'Senior QA Engineer');

    deleteJson("/api/v1/admin/mock-blueprints/{$id}")->assertNoContent();
    expect(MockBlueprint::withoutGlobalScopes()->where('id', $id)->exists())->toBeFalse();
});

it('denies blueprint admin to students and updates topics with mock_enabled', function () {
    $this->seed(RolePermissionSeeder::class);
    ['student' => $student] = mockReadyStudent($this->tenant);
    Sanctum::actingAs($student);
    getJson('/api/v1/admin/mock-blueprints')->assertForbidden();

    $topic = withinTenant($this->tenant, function () {
        $course = Course::factory()->create(['tenant_id' => $this->tenant->id]);
        $module = Module::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id]);

        return Topic::factory()->create(['tenant_id' => $this->tenant->id, 'module_id' => $module->id]);
    });

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    patchJson("/api/v1/admin/topics/{$topic->id}", ['mock_enabled' => true])->assertOk();
    expect($topic->fresh()->mock_enabled)->toBeTrue();
});
