<?php

declare(strict_types=1);

use App\Actions\Employers\MoveApplicationStage;
use App\Enums\EmployerApplicationStage;
use App\Models\CvProfile;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Models\InAppNotification;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Acme Technologies']);
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'title' => 'Data Engineer',
    ]);
});

it('returns the candidate command centre in one round trip', function (): void {
    CvProfile::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'data' => ['skills' => ['python'], 'summary' => 'Trained on the BrowseJobs programme.'],
    ]);

    EmployerJobApplication::factory()->for($this->tenant)->graded(78)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    Sanctum::actingAs($this->candidate);

    $response = $this->getJson('/api/v1/me/candidate-dashboard')->assertOk();

    $response->assertJsonPath('data.applications.0.title', 'Data Engineer')
        ->assertJsonPath('data.applications.0.company', 'Acme Technologies')
        ->assertJsonPath('data.applications.0.mock_score', 78)
        ->assertJsonPath('data.verification.badge', false);

    // Completeness is a list of fixable items, not an opaque score.
    expect($response->json('data.profile_completeness.items'))->not->toBeEmpty()
        ->and($response->json('data.profile_completeness.pct'))->toBeGreaterThan(0);
});

it('withholds the cohort comparison until the sample can support it', function (): void {
    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    Sanctum::actingAs($this->candidate);

    // Three offers is not a cohort. Returning null is the honest answer, and
    // the UI renders that rather than a placeholder percentage.
    $this->getJson('/api/v1/me/candidate-dashboard')
        ->assertOk()
        ->assertJsonPath('data.cohort', null);
});

it('reports the cohort median once enough offers exist, without naming anyone', function (): void {
    foreach (range(1, 14) as $i) {
        EmployerJobApplication::factory()->for($this->tenant)->graded(70 + $i)->create([
            'employer_job_id' => $this->job->id,
            'candidate_id' => User::factory()->for($this->tenant)->create()->id,
            'stage' => EmployerApplicationStage::Offer->value,
        ]);
    }

    Sanctum::actingAs($this->candidate);

    $cohort = $this->getJson('/api/v1/me/candidate-dashboard')->assertOk()->json('data.cohort');

    expect($cohort['sample'])->toBe(14)
        ->and($cohort['offer_median_mock'])->toBeGreaterThan(70);

    // Aggregate only — no identities travel with the comparison.
    expect(array_keys($cohort))
        ->toBe(['offer_median_mock', 'your_best_mock', 'sample', 'verified_share_pct']);
});

it('shows a candidate only their own applications', function (): void {
    $stranger = User::factory()->for($this->tenant)->create();
    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $stranger->id,
    ]);

    Sanctum::actingAs($this->candidate);

    $this->getJson('/api/v1/me/candidate-dashboard')
        ->assertOk()
        ->assertJsonCount(0, 'data.applications');
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/me/candidate-dashboard')->assertUnauthorized();
});

it('notifies the candidate when an employer moves them, including a rejection', function (): void {
    $application = EmployerJobApplication::factory()->for($this->tenant)->graded(80)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    $recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);

    app(MoveApplicationStage::class)
        ->handle($application, EmployerApplicationStage::Shortlisted, $recruiter);

    app(MoveApplicationStage::class)
        ->handle($application->fresh(), EmployerApplicationStage::Rejected, $recruiter, 'Stronger candidates on core SQL.');

    $titles = InAppNotification::withoutGlobalScopes()
        ->where('user_id', $this->candidate->id)
        ->pluck('title');

    // Silence is the worst part of applying — a rejection is still told.
    expect($titles)->toHaveCount(2)
        ->and($titles->first())->toContain('Shortlisted')
        ->and($titles->last())->toContain('Not moving forward');
});

it('does not notify for the system-assigned graded stage', function (): void {
    $application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    app(MoveApplicationStage::class)
        ->handle($application, EmployerApplicationStage::Graded, null);

    expect(InAppNotification::withoutGlobalScopes()
        ->where('user_id', $this->candidate->id)
        ->exists())->toBeFalse();
});
