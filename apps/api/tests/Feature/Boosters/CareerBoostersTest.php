<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Models\BatchMember;
use App\Models\CodeSubmission;
use App\Models\Course;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Lesson;
use App\Models\MockBlueprint;
use App\Models\Module;
use App\Models\RealInterviewQuestion;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Entitlements\EntitlementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'name' => 'Asha']);
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);

    $this->grantCareerPlus = fn () => withinTenant($this->tenant, fn () => app(EntitlementService::class)
        ->grantEntitlement($this->student, 'career_plus', 'career_plus', now()->addDays(30)));
});

it('shows career_plus false and no boosters before subscribing', function () {
    Sanctum::actingAs($this->student);
    $this->getJson('/api/v1/me/boosters')->assertOk()
        ->assertJsonPath('data.career_plus', false)
        ->assertJsonPath('data.linkedin', null);
});

it('gates every booster behind Career+', function () {
    Sanctum::actingAs($this->student);
    $this->postJson('/api/v1/me/boosters/linkedin')->assertForbidden();
    $this->postJson('/api/v1/me/boosters/github')->assertForbidden();
    $this->postJson('/api/v1/me/boosters/prep-pack')->assertForbidden();
});

it('optimises LinkedIn from real facts when the model returns JSON', function () {
    ($this->grantCareerPlus)();
    $this->fake->reply = '{"headline":"Data Analyst | SQL","about":"I build things.","top_skills":["SQL"],"tips":["Add projects"]}';
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/boosters/linkedin')->assertOk()
        ->assertJsonPath('data.source', 'ai')
        ->assertJsonPath('data.content.headline', 'Data Analyst | SQL');

    // Persisted and returned by index.
    $this->getJson('/api/v1/me/boosters')->assertOk()->assertJsonPath('data.linkedin.content.headline', 'Data Analyst | SQL');
});

it('falls back to a deterministic LinkedIn draft when the model returns garbage', function () {
    ($this->grantCareerPlus)();
    $this->fake->reply = 'I cannot help with that.';
    Sanctum::actingAs($this->student);

    $body = $this->postJson('/api/v1/me/boosters/linkedin')->assertOk()->assertJsonPath('data.source', 'fallback');
    expect($body->json('data.content.headline'))->not->toBeEmpty();
});

it('builds a GitHub portfolio from the student passed labs', function () {
    ($this->grantCareerPlus)();
    withinTenant($this->tenant, function () {
        $module = Module::query()->create(['course_id' => Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da'])->id, 'name' => 'M', 'position' => 1]);
        $topic = Topic::query()->create(['module_id' => $module->id, 'name' => 'T', 'position' => 1]);
        $lesson = Lesson::query()->create(['topic_id' => $topic->id, 'title' => 'FizzBuzz lab', 'type' => 'coding_lab', 'position' => 1]);
        CodeSubmission::query()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->student->id, 'lesson_id' => $lesson->id,
            'language' => 'python', 'source' => 'print("hi")', 'kind' => 'submit', 'passed_tests' => 3, 'total_tests' => 3,
        ]);
    });
    $this->fake->reply = '{"intro":"My work","projects":[{"name":"FizzBuzz","summary":"Prints","tech":["python"],"highlights":["tests pass"]}]}';
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/boosters/github')->assertOk()
        ->assertJsonPath('data.source', 'ai')
        ->assertJsonPath('data.content.projects.0.name', 'FizzBuzz');
});

it('builds a prep pack from approved real interview questions for the role', function () {
    ($this->grantCareerPlus)();
    withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DA', 'name' => 'Data', 'slug' => 'da']);
        $batch = $course->batches()->create(['number' => 'DA-1', 'type' => BatchType::Paid->value]);
        BatchMember::query()->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled']);
        MockBlueprint::query()->create(['course_id' => $course->id, 'role_title' => 'Data Analyst', 'competencies' => ['SQL'], 'opening_question' => 'Q', 'is_active' => true]);
        RealInterviewQuestion::query()->create([
            'tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'role_title' => 'Data Analyst',
            'question' => 'Explain a window function.', 'normalized_question' => 'explain window function',
            'fingerprint' => 'fp1', 'topic_tags' => ['SQL'], 'status' => 'approved', 'asked_count' => 5,
        ]);
    });
    $this->fake->reply = '{"role":"Data Analyst","focus_areas":[{"topic":"SQL","questions":["Explain a window function."],"tips":["Structure it"]}]}';
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/boosters/prep-pack')->assertOk()
        ->assertJsonPath('data.content.focus_areas.0.topic', 'SQL');
});

it('prep pack is honestly empty with no bank coverage', function () {
    ($this->grantCareerPlus)();
    Sanctum::actingAs($this->student);
    // No blueprint/questions → empty focus areas, fallback source, no crash.
    $this->postJson('/api/v1/me/boosters/prep-pack')->assertOk()
        ->assertJsonPath('data.source', 'fallback')
        ->assertJsonPath('data.content.focus_areas', []);
});

it('tailors a CV for an application and links it (Career+)', function () {
    ($this->grantCareerPlus)();
    $this->fake->reply = '{"summary":"S","skills":["SQL"],"experience":[],"projects":[],"education":[],"certifications":[],"headline":"H"}';
    [$application] = withinTenant($this->tenant, function () {
        $posting = JobPosting::factory()->for($this->tenant)->create(['description' => 'We need strong SQL and Python.']);
        $app = JobApplication::factory()->for($this->tenant)->create(['user_id' => $this->student->id, 'job_posting_id' => $posting->id]);

        return [$app];
    });
    Sanctum::actingAs($this->student);

    $this->postJson("/api/v1/me/applications/{$application->id}/tailor")->assertOk()
        ->assertJsonStructure(['data' => ['cv_document_id', 'title']]);

    expect($application->fresh()->cv_document_id)->not->toBeNull();
});

it('denies tailoring without Career+', function () {
    $application = withinTenant($this->tenant, function () {
        $posting = JobPosting::factory()->for($this->tenant)->create();

        return JobApplication::factory()->for($this->tenant)->create(['user_id' => $this->student->id, 'job_posting_id' => $posting->id]);
    });
    Sanctum::actingAs($this->student);
    $this->postJson("/api/v1/me/applications/{$application->id}/tailor")->assertForbidden();
});

it('404s tailoring another student application', function () {
    ($this->grantCareerPlus)();
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $application = withinTenant($this->tenant, function () use ($other) {
        $posting = JobPosting::factory()->for($this->tenant)->create();

        return JobApplication::factory()->for($this->tenant)->create(['user_id' => $other->id, 'job_posting_id' => $posting->id]);
    });
    Sanctum::actingAs($this->student);
    $this->postJson("/api/v1/me/applications/{$application->id}/tailor")->assertNotFound();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/me/boosters')->assertUnauthorized();
});
