<?php

declare(strict_types=1);

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

it('publishes the real description and withholds salary unless the employer opted in', function (): void {
    $response = $this->getJson('http://acme-board.test/api/v1/job-board')->assertOk();

    expect($response->json('data.internal.0.description'))->toBe($this->job->description)
        ->and($response->json('data.internal.0'))->not->toHaveKey('ctc_min_paise')
        ->and($response->json('data.external.0.description'))->not->toBe('')
        ->and($response->json('data.external.0.expires_at'))->not->toBeNull();

    $this->job->update([
        'ctc_visible' => true,
        'ctc_min_paise' => 80000000,
        'ctc_max_paise' => 120000000,
    ]);

    $this->getJson('http://acme-board.test/api/v1/job-board')
        ->assertOk()
        ->assertJsonPath('data.internal.0.ctc_min_paise', 80000000)
        ->assertJsonPath('data.internal.0.ctc_max_paise', 120000000);
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

it('starts a JD mock before the candidate applies and resumes the same attempt', function (): void {
    Sanctum::actingAs($this->candidate);

    $first = $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")
        ->assertStatus(201)
        ->json('data.mock_id');
    $second = $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")
        ->assertStatus(201)
        ->json('data.mock_id');

    expect($second)->toBe($first)
        ->and(EmployerJobApplication::query()->where('candidate_id', $this->candidate->id)->exists())->toBeFalse();
});

it('refuses another JD mock once the free attempt cap is used', function (): void {
    withinTenant($this->tenant, fn () => app(EntitlementService::class)->settings()->update(['employer_mock_attempts_per_job' => 1]));

    $blueprint = MockBlueprint::query()->create([
        'tenant_id' => $this->tenant->id,
        'employer_job_id' => $this->job->id,
        'role_title' => $this->job->title,
        'competencies' => ['python'],
        'opening_question' => 'Tell me about yourself.',
        'is_active' => false,
    ]);
    MockInterview::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_TEXT,
        'status' => MockInterview::STATUS_COMPLETED,
        'overall_score' => 60,
        'started_at' => now()->subMinutes(20),
        'completed_at' => now(),
    ]);

    Sanctum::actingAs($this->candidate);

    $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/mock")->assertStatus(422);
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
