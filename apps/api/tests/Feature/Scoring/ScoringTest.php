<?php

declare(strict_types=1);

use App\Actions\Scoring\ComputeStudentScores;
use App\Actions\Scoring\RecomputeAllScores;
use App\Actions\Support\TicketStudentContext;
use App\Enums\ActivityType;
use App\Enums\BatchMemberStatus;
use App\Models\AccessBlock;
use App\Models\ActivityEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Module;
use App\Models\ScoreSnapshot;
use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\TopicCompletion;
use App\Models\User;
use App\Support\Scoring\ScoreCalculator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/* ---- Local seed helpers (rule-based scoring reads real telemetry) ---- */

function scoringStudent(Tenant $tenant): User
{
    return User::factory()->for($tenant)->create(['user_type' => 'student']);
}

function scoringEnroll(Tenant $tenant, User $student): Course
{
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id]);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id,
        'user_id' => $student->id,
        'status' => BatchMemberStatus::Enrolled->value,
    ]);

    return $course;
}

/** A module with `$topics` topics, `$done` of them completed by the student. */
function scoringModule(Tenant $tenant, Course $course, User $student, int $topics, int $done, int $pos): Module
{
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id, 'position' => $pos]);
    $rows = Topic::factory()->count($topics)->for($tenant)->create(['module_id' => $module->id]);

    foreach ($rows->take($done) as $topic) {
        TopicCompletion::create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'topic_id' => $topic->id,
            'completed_at' => now(),
        ]);
    }

    return $module;
}

/** Login activity on each of the last `$days` days — a fully-engaged student. */
function scoringEngage(Tenant $tenant, User $student, int $days, int $perDay = 3): void
{
    for ($d = 0; $d < $days; $d++) {
        for ($i = 0; $i < $perDay; $i++) {
            ActivityEvent::factory()->for($tenant)->create([
                'user_id' => $student->id,
                'type' => ActivityType::Login->value,
                'occurred_at' => now()->copy()->subDays($d)->setTime(9, 0),
            ]);
        }
    }
}

beforeEach(function () {
    Carbon::setTestNow('2026-07-16 12:00:00');
    $this->tenant = Tenant::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

/* ---- ScoreCalculator: deterministic rule-based scores ---- */

it('computes deterministic scores from seeded telemetry', function () {
    $student = scoringStudent($this->tenant);
    $course = scoringEnroll($this->tenant, $student);
    $strong = scoringModule($this->tenant, $course, $student, 4, 3, 1); // 75% → strong
    $weak = scoringModule($this->tenant, $course, $student, 4, 0, 2);   // 0%  → weak
    scoringEngage($this->tenant, $student, 7);                          // engagement 100, streak 7

    $data = app(ScoreCalculator::class)->computeFor($student);

    expect($data['engagement'])->toBe(100)
        ->and($data['streak_days'])->toBe(7)
        ->and($data['pri'])->toBe(49)          // round(38*.5 + 100*.3 + 0*.2)
        ->and($data['risk_dropout'])->toBe(0)  // engaged, not stalled, no fee
        ->and($data['risk_placement'])->toBe(58)
        ->and($data['mastery'])->toHaveCount(2)
        ->and($data['mastery'][0])->toMatchArray(['name' => $strong->name, 'pct' => 75, 'band' => 'strong'])
        ->and($data['mastery'][1])->toMatchArray(['name' => $weak->name, 'pct' => 0, 'band' => 'weak'])
        ->and($data['went_well'])->toBe([$strong->name])
        ->and($data['needs_work'])->toBe([$weak->name])
        ->and($data['next_action']['key'])->toBe('practice_module')
        ->and($data['red_flags'])->toBe([]);
});

it('scores a disengaged, fee-blocked student as high dropout risk', function () {
    $student = scoringStudent($this->tenant);
    $course = scoringEnroll($this->tenant, $student);
    scoringModule($this->tenant, $course, $student, 2, 0, 1); // weak, but fee wins
    AccessBlock::factory()->for($this->tenant)->create(['user_id' => $student->id]);

    $data = app(ScoreCalculator::class)->computeFor($student);

    expect($data['engagement'])->toBe(0)
        ->and($data['risk_dropout'])->toBe(75)          // .55*100 + .20*100
        ->and($data['next_action']['key'])->toBe('clear_fee') // fee beats the weak module
        ->and($data['red_flags'])->toContain('fee_overdue')
        ->and($data['red_flags'])->toContain('low_engagement');
});

it('prioritises re-engagement when there are no weak modules but engagement is dead', function () {
    $student = scoringStudent($this->tenant);
    $course = scoringEnroll($this->tenant, $student);
    scoringModule($this->tenant, $course, $student, 1, 1, 1); // 100% → strong, no weak module
    // no activity events → engagement 0, streak 0

    $data = app(ScoreCalculator::class)->computeFor($student);

    expect($data['needs_work'])->toBe([])
        ->and($data['next_action']['key'])->toBe('re_engage');
});

it('recommends a mock interview when the student is engaged and ready', function () {
    $student = scoringStudent($this->tenant);
    $course = scoringEnroll($this->tenant, $student);
    scoringModule($this->tenant, $course, $student, 1, 1, 1); // 100% mastery
    scoringEngage($this->tenant, $student, 7);                // engagement 100 → pri 80

    $data = app(ScoreCalculator::class)->computeFor($student);

    expect($data['pri'])->toBeGreaterThanOrEqual(70)
        ->and($data['next_action']['key'])->toBe('book_mock');
});

/* ---- ComputeStudentScores: upsert latest + dated snapshot ---- */

it('upserts a student_scores row and today\'s snapshot, idempotently', function () {
    $student = scoringStudent($this->tenant);
    $course = scoringEnroll($this->tenant, $student);
    scoringModule($this->tenant, $course, $student, 4, 3, 1);
    scoringEngage($this->tenant, $student, 7);

    app(ComputeStudentScores::class)->handle($student);
    app(ComputeStudentScores::class)->handle($student); // same day, re-run

    expect(StudentScore::withoutGlobalScopes()->where('user_id', $student->id)->count())->toBe(1)
        ->and(ScoreSnapshot::withoutGlobalScopes()->where('user_id', $student->id)->count())->toBe(1);

    $score = StudentScore::withoutGlobalScopes()->where('user_id', $student->id)->first();
    expect($score->pri)->toBe(68)->and($score->tenant_id)->toBe($this->tenant->id); // round(75*.5 + 100*.3)
});

it('keeps a separate snapshot per day for the PRI trajectory', function () {
    $student = scoringStudent($this->tenant);
    $course = scoringEnroll($this->tenant, $student);
    scoringModule($this->tenant, $course, $student, 4, 3, 1);
    scoringEngage($this->tenant, $student, 7);

    app(ComputeStudentScores::class)->handle($student);
    Carbon::setTestNow('2026-07-17 12:00:00');
    app(ComputeStudentScores::class)->handle($student);

    expect(ScoreSnapshot::withoutGlobalScopes()->where('user_id', $student->id)->count())->toBe(2);
});

/* ---- scores:recompute: active students only, per tenant ---- */

it('rescoring covers only active students and is tenant-scoped', function () {
    $studentA = scoringStudent($this->tenant);
    scoringEnroll($this->tenant, $studentA);

    // An enrolled student in a second tenant.
    $tenantB = Tenant::factory()->create();
    $studentB = scoringStudent($tenantB);
    scoringEnroll($tenantB, $studentB);

    // A student with no occupying batch membership must not be scored.
    $idle = scoringStudent($this->tenant);

    $summary = app(RecomputeAllScores::class)->handle();

    expect($summary['scored'])->toBe(2)
        ->and(StudentScore::withoutGlobalScopes()->where('user_id', $studentA->id)->exists())->toBeTrue()
        ->and(StudentScore::withoutGlobalScopes()->where('user_id', $studentB->id)->exists())->toBeTrue()
        ->and(StudentScore::withoutGlobalScopes()->where('user_id', $idle->id)->exists())->toBeFalse();
});

it('runs via the scheduled command', function () {
    $student = scoringStudent($this->tenant);
    scoringEnroll($this->tenant, $student);

    $this->artisan('scores:recompute')->assertSuccessful();

    expect(StudentScore::withoutGlobalScopes()->where('user_id', $student->id)->exists())->toBeTrue();
});

/* ---- GET me/coach ---- */

it('serves the coach panel for the authenticated student', function () {
    $student = scoringStudent($this->tenant);
    $course = scoringEnroll($this->tenant, $student);
    scoringModule($this->tenant, $course, $student, 4, 3, 1);
    scoringEngage($this->tenant, $student, 7);
    Sanctum::actingAs($student);

    getJson('/api/v1/me/coach')
        ->assertOk()
        ->assertJsonPath('data.pri', 68)
        ->assertJsonPath('data.risk.dropout_band', 'low')
        ->assertJsonPath('data.next_action.key', 'next_topic') // engaged, no weak module, pri < 70
        ->assertJsonStructure(['data' => ['pri', 'engagement', 'streak_days', 'mastery', 'went_well', 'needs_work', 'next_action', 'trajectory', 'risk' => ['dropout', 'placement', 'dropout_band']]]);

    // The panel persists scores scoped to the acting student's tenant.
    $score = StudentScore::withoutGlobalScopes()->where('user_id', $student->id)->first();
    expect($score->tenant_id)->toBe($this->tenant->id);
});

it('rejects the coach panel for guests', function () {
    getJson('/api/v1/me/coach')->assertUnauthorized();
});

/* ---- GET admin/risk ---- */

it('serves the risk dashboard to a counselor, tenant-scoped and risk-sorted', function () {
    $this->seed(RolePermissionSeeder::class);
    $counselor = counselorIn($this->tenant);

    $s1 = scoringStudent($this->tenant);
    StudentScore::factory()->for($this->tenant)->create(['user_id' => $s1->id, 'risk_dropout' => 80, 'red_flags' => ['fee_overdue']]);
    $s2 = scoringStudent($this->tenant);
    StudentScore::factory()->for($this->tenant)->create(['user_id' => $s2->id, 'risk_dropout' => 20]);

    // Another tenant's score must never appear.
    $tenantB = Tenant::factory()->create();
    $sb = scoringStudent($tenantB);
    StudentScore::factory()->for($tenantB)->create(['user_id' => $sb->id, 'risk_dropout' => 99]);

    Sanctum::actingAs($counselor);

    $res = getJson('/api/v1/admin/risk')
        ->assertOk()
        ->assertJsonPath('data.high_risk', 1)
        ->assertJsonPath('data.students.0.risk_dropout', 80) // highest risk first
        ->assertJsonCount(2, 'data.students');

    expect(collect($res->json('data.students'))->pluck('user_id'))->not->toContain($sb->id);
    expect($res->json('data.students.0.script'))->toContain('fee'); // intervention script from red flag
});

it('surfaces rising-risk movers against the previous snapshot', function () {
    $this->seed(RolePermissionSeeder::class);
    $counselor = counselorIn($this->tenant);

    $student = scoringStudent($this->tenant);
    StudentScore::factory()->for($this->tenant)->create(['user_id' => $student->id, 'risk_dropout' => 70]);
    ScoreSnapshot::factory()->for($this->tenant)->create([
        'user_id' => $student->id,
        'captured_on' => now()->copy()->subDay()->startOfDay(),
        'risk_dropout' => 40,
    ]);

    Sanctum::actingAs($counselor);

    getJson('/api/v1/admin/risk')
        ->assertOk()
        ->assertJsonPath('data.movers.0.mover', 30); // 70 - 40
});

it('denies the risk dashboard to staff without manage-leads', function () {
    $this->seed(RolePermissionSeeder::class);
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $trainer->assignRole('trainer');

    Sanctum::actingAs($trainer);

    getJson('/api/v1/admin/risk')->assertForbidden();
});

/* ---- P2.7 support desk now surfaces the risk score ---- */

it('includes the student risk score in the support desk context', function () {
    $student = scoringStudent($this->tenant);
    StudentScore::factory()->for($this->tenant)->create(['user_id' => $student->id, 'risk_dropout' => 42]);

    $context = withinTenant($this->tenant, fn () => app(TicketStudentContext::class)->handle($student));

    expect($context['risk_score'])->toBe(42);
});
