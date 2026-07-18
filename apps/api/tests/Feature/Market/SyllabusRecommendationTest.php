<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\MarketJd;
use App\Models\Module;
use App\Models\RealInterviewQuestion;
use App\Models\SyllabusRecommendation;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
});

function trainerFor(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('trainer');

    return $user;
}

/** A course whose outline already covers Python, plus market + bank signal. */
function seededCourse(Tenant $tenant): Course
{
    return withinTenant($tenant, function () use ($tenant) {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Engineering', 'slug' => 'de']);
        $module = Module::query()->create(['course_id' => $course->id, 'name' => 'Python foundations', 'position' => 1]);
        Topic::query()->create(['module_id' => $module->id, 'name' => 'Python basics and pandas', 'position' => 1]);

        // Market demand: python (covered) + dbt/airflow (not covered).
        foreach ([['python', 'sql', 'dbt', 'airflow'], ['python', 'dbt'], ['dbt', 'airflow']] as $i => $skills) {
            MarketJd::factory()->for($tenant)->create([
                'course_id' => $course->id,
                'role_title' => 'Data Engineer',
                'raw_jd' => 'JD '.$i.' '.implode(' ', $skills),
                'fingerprint' => 'fp-'.$i,
                'extracted_skills' => $skills,
            ]);
        }

        // Interview bank: dbt is a frequent topic and a failure point.
        RealInterviewQuestion::query()->create([
            'tenant_id' => $tenant->id, 'course_id' => $course->id, 'role_title' => 'Data Engineer',
            'question' => 'Explain incremental models in dbt.', 'normalized_question' => 'incremental models dbt',
            'fingerprint' => 'q1', 'topic_tags' => ['dbt'], 'status' => 'approved', 'asked_count' => 7,
            'struggle_points' => 'Candidates fumble incremental logic.', 'outcome' => 'rejected',
        ]);

        return $course;
    });
}

it('generates an evidence-backed report on the AI path', function () {
    $course = seededCourse($this->tenant);
    $this->fake->reply = json_encode([
        'summary' => 'Add dbt and Airflow — both in demand and under-covered.',
        'items' => [
            ['action' => 'add', 'topic' => 'dbt', 'rationale' => 'In most JDs and a top interview topic.', 'priority' => 'high'],
            ['action' => 'add', 'topic' => 'airflow', 'rationale' => 'Frequently required, not in the outline.', 'priority' => 'medium'],
        ],
    ], JSON_THROW_ON_ERROR);
    Sanctum::actingAs(trainerFor($this->tenant));

    postJson("/api/v1/admin/courses/{$course->id}/syllabus-recommendations")->assertCreated()
        ->assertJsonPath('data.content_source', 'ai')
        ->assertJsonPath('data.items.0.topic', 'dbt');

    $report = SyllabusRecommendation::withoutGlobalScopes()->sole();
    expect($report->evidence['market_demand'])->not->toBeEmpty()
        ->and($report->market_sample)->toBe(3);
});

it('falls back to a deterministic gap analysis when the model returns garbage', function () {
    $course = seededCourse($this->tenant);
    $this->fake->reply = 'not json at all';
    Sanctum::actingAs(trainerFor($this->tenant));

    postJson("/api/v1/admin/courses/{$course->id}/syllabus-recommendations")->assertCreated()
        ->assertJsonPath('data.content_source', 'fallback');

    $report = SyllabusRecommendation::withoutGlobalScopes()->sole();
    $topics = array_column($report->items, 'topic');
    // dbt/airflow are demanded but absent from the outline; python is covered, so excluded.
    expect($topics)->toContain('dbt')
        ->and($topics)->not->toContain('python');
});

it('approves a report, records the reviewer, and audits it', function () {
    $course = seededCourse($this->tenant);
    $report = SyllabusRecommendation::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    $trainer = trainerFor($this->tenant);
    Sanctum::actingAs($trainer);

    postJson("/api/v1/admin/syllabus-recommendations/{$report->id}/approve", ['note' => 'Adding to Module 4.'])
        ->assertOk()->assertJsonPath('data.status', 'approved');

    expect($report->fresh()->reviewed_by)->toBe($trainer->id)
        ->and($report->fresh()->review_note)->toBe('Adding to Module 4.');
    expect(AuditLog::withoutGlobalScopes()->where('action', 'syllabus_recommendation.approved')->exists())->toBeTrue();
});

it('rejects a report with a note', function () {
    $course = seededCourse($this->tenant);
    $report = SyllabusRecommendation::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    Sanctum::actingAs(trainerFor($this->tenant));

    postJson("/api/v1/admin/syllabus-recommendations/{$report->id}/reject", ['note' => 'Market too thin.'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');
    expect($report->fresh()->status)->toBe('rejected');
});

it('cannot re-review an already decided report', function () {
    $course = seededCourse($this->tenant);
    $report = SyllabusRecommendation::factory()->for($this->tenant)->create([
        'course_id' => $course->id, 'status' => 'approved',
    ]);
    Sanctum::actingAs(trainerFor($this->tenant));

    postJson("/api/v1/admin/syllabus-recommendations/{$report->id}/reject")->assertStatus(422);
});

it('gates recommendations behind manage-curriculum', function () {
    $course = seededCourse($this->tenant);
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));

    getJson('/api/v1/admin/syllabus-recommendations')->assertForbidden();
    postJson("/api/v1/admin/courses/{$course->id}/syllabus-recommendations")->assertForbidden();
});

it('does not expose another tenant report', function () {
    $other = Tenant::factory()->create();
    $otherCourse = seededCourse($other);
    $report = SyllabusRecommendation::factory()->for($other)->create(['course_id' => $otherCourse->id]);

    Sanctum::actingAs(trainerFor($this->tenant));
    postJson("/api/v1/admin/syllabus-recommendations/{$report->id}/approve")->assertNotFound();
    getJson('/api/v1/admin/syllabus-recommendations')->assertOk()->assertJsonPath('data.reports', []);
});

it('requires authentication', function () {
    getJson('/api/v1/admin/syllabus-recommendations')->assertUnauthorized();
});
