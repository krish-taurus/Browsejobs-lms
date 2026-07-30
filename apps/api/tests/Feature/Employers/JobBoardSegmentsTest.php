<?php

declare(strict_types=1);

use App\Enums\EntitlementFeature;
use App\Events\MockCompleted;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Models\JobFeedItem;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->domain('acme-board.test')->create();
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Acme Technologies']);
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'title' => 'Data Engineer',
        'skills' => ['python', 'sql'],
    ]);

    JobFeedItem::factory()->for($this->tenant)->create([
        'title' => 'Senior Data Engineer',
        'company' => 'Someone Else Ltd',
        'status' => JobFeedItem::STATUS_ACTIVE,
    ]);
});

it('keeps employer postings and market roles in separate segments', function (): void {
    Sanctum::actingAs($this->candidate);

    $response = $this->getJson('http://acme-board.test/api/v1/job-board')->assertOk();

    expect($response->json('data.internal'))->toHaveCount(1)
        ->and($response->json('data.external'))->toHaveCount(1)
        ->and($response->json('data.internal.0.company'))->toBe('Acme Technologies')
        ->and($response->json('data.external.0.company'))->toBe('Someone Else Ltd');

    // The distinction the segments exist to carry: only internal postings can
    // be applied to here, and they say whether their mock is ready.
    expect($response->json('data.internal.0'))->toHaveKey('mock_ready')
        ->and($response->json('data.external.0'))->not->toHaveKey('mock_ready');
});

it('marks internal postings the candidate has already applied to', function (): void {
    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    Sanctum::actingAs($this->candidate);

    $this->getJson('http://acme-board.test/api/v1/job-board')
        ->assertOk()
        ->assertJsonPath('data.internal.0.has_applied', true);
});

it('serves the board to signed-out visitors without leaking application state', function (): void {
    $response = $this->getJson('http://acme-board.test/api/v1/job-board')->assertOk();

    expect($response->json('data.internal'))->toHaveCount(1)
        ->and($response->json('data.internal.0.has_applied'))->toBeFalse();
});

it('filters both segments by the same search term', function (): void {
    Sanctum::actingAs($this->candidate);

    $response = $this->getJson('http://acme-board.test/api/v1/job-board?q=Senior')->assertOk();

    expect($response->json('data.internal'))->toHaveCount(0)
        ->and($response->json('data.external'))->toHaveCount(1);
});

it('never shows another tenant postings', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = EmployerWorkspace::factory()->for($otherTenant)->create();
    EmployerJob::factory()->for($otherTenant)->published()->create([
        'employer_workspace_id' => $otherWorkspace->id,
        'title' => 'Secret Role',
    ]);

    Sanctum::actingAs($this->candidate);

    $titles = collect($this->getJson('http://acme-board.test/api/v1/job-board')->json('data.internal'))->pluck('title');

    expect($titles)->not->toContain('Secret Role');
});

it('spends a mock credit to start a JD mock and refuses when the wallet is empty', function (): void {
    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    Sanctum::actingAs($this->candidate);

    // No credits yet: an empty wallet is an offer, not a validation error.
    $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")
        ->assertStatus(402)
        ->assertJsonPath('error.code', 'mock_credits_required');

    app(EntitlementService::class)->grantCredits(
        $this->candidate,
        EntitlementFeature::VoiceMock->value,
        2,
        'test',
    );

    $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['mock_id']]);

    expect(app(EntitlementService::class)->balance($this->candidate, EntitlementFeature::VoiceMock->value))
        ->toBe(1);
});

it('resumes an attempt in flight instead of charging a second credit', function (): void {
    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    app(EntitlementService::class)->grantCredits(
        $this->candidate,
        EntitlementFeature::VoiceMock->value,
        5,
        'test',
    );

    Sanctum::actingAs($this->candidate);

    $first = $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")->json('data.mock_id');
    $second = $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")->json('data.mock_id');

    expect($second)->toBe($first)
        ->and(app(EntitlementService::class)->balance($this->candidate, EntitlementFeature::VoiceMock->value))
        ->toBe(4);
});

it('refuses a mock for a role the candidate has not applied to', function (): void {
    app(EntitlementService::class)->grantCredits(
        $this->candidate,
        EntitlementFeature::VoiceMock->value,
        5,
        'test',
    );

    Sanctum::actingAs($this->candidate);

    $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")->assertStatus(422);

    // The credit is never spent on a refused attempt.
    expect(app(EntitlementService::class)->balance($this->candidate, EntitlementFeature::VoiceMock->value))
        ->toBe(5);
});

/** Finish a mock spun from this JD's blueprint, as the engine would. */
function finishJdMock(User $candidate, EmployerJob $job, int $score): MockInterview
{
    $blueprint = MockBlueprint::query()->firstOrCreate(
        ['tenant_id' => $job->tenant_id, 'employer_job_id' => $job->id],
        [
            'role_title' => $job->title,
            'competencies' => ['python'],
            'opening_question' => 'Tell me about yourself.',
            'is_active' => false,
        ],
    );

    $interview = MockInterview::query()->create([
        'tenant_id' => $candidate->tenant_id,
        'user_id' => $candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_TEXT,
        'status' => MockInterview::STATUS_COMPLETED,
        'overall_score' => $score,
        'started_at' => now()->subMinutes(20),
        'completed_at' => now(),
    ]);

    MockCompleted::dispatch($candidate, $interview);

    return $interview;
}

it('carries a finished mock score onto the application, best attempt winning', function (): void {
    $application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    $strong = finishJdMock($this->candidate, $this->job, 82);
    expect($application->fresh()->mock_score)->toBe(82);

    // A weaker retake must never damage the score the employer already saw.
    finishJdMock($this->candidate, $this->job, 51);
    expect($application->fresh()->mock_score)->toBe(82)
        ->and($application->fresh()->mock_interview_id)->toBe($strong->id);
});

it('ignores a mock that was not spun from an employer JD', function (): void {
    $application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $this->tenant->id,
        'role_title' => 'Course practice',
        'competencies' => ['python'],
        'opening_question' => 'Tell me about yourself.',
    ]);

    $interview = MockInterview::query()->create([
        'tenant_id' => $this->candidate->tenant_id,
        'user_id' => $this->candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_TEXT,
        'status' => MockInterview::STATUS_COMPLETED,
        'overall_score' => 95,
        'started_at' => now()->subMinutes(20),
        'completed_at' => now(),
    ]);

    MockCompleted::dispatch($this->candidate, $interview);

    // Course practice must never silently become an employer-facing grade.
    expect($application->fresh()->mock_score)->toBeNull();
});
