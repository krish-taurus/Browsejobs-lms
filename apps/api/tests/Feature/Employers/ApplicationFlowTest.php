<?php

declare(strict_types=1);

use App\Actions\Employers\RecordApplicationGrade;
use App\Enums\EmployerApplicationStage;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('lets a candidate browse and apply free, once', function (): void {
    Sanctum::actingAs($this->candidate);

    $this->getJson('/api/v1/me/employer-jobs')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->job->id);

    $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/apply")
        ->assertCreated()
        ->assertJsonPath('data.stage', 'applied');

    // Duplicate application is refused.
    $this->postJson("/api/v1/me/employer-jobs/{$this->job->id}/apply")
        ->assertUnprocessable();

    // Draft jobs are invisible and un-applicable.
    $draft = EmployerJob::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);
    $this->getJson("/api/v1/me/employer-jobs/{$draft->id}")->assertNotFound();
    $this->postJson("/api/v1/me/employer-jobs/{$draft->id}/apply")->assertUnprocessable();
});

it('ranks graded applications above ungraded regardless of recency', function (): void {
    $ungraded = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => User::factory()->for($this->tenant)->create()->id,
        'created_at' => now(),
    ]);
    $midScore = EmployerJobApplication::factory()->for($this->tenant)->graded(70)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => User::factory()->for($this->tenant)->create()->id,
        'created_at' => now()->subDays(3),
    ]);
    $topScore = EmployerJobApplication::factory()->for($this->tenant)->graded(91)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => User::factory()->for($this->tenant)->create()->id,
        'created_at' => now()->subDays(5),
    ]);

    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/applications")
        ->assertOk();

    expect($response->json('data.*.id'))->toBe([$topScore->id, $midScore->id, $ungraded->id]);
});

it('hides candidate contact details until shortlisted', function (): void {
    $application = EmployerJobApplication::factory()->for($this->tenant)->graded()->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/applications/{$application->id}")
        ->assertOk()
        ->assertJsonPath('data.candidate.email', null);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/applications/{$application->id}/stage", [
        'stage' => 'shortlisted',
    ])->assertOk()
        ->assertJsonPath('data.candidate.email', $this->candidate->email);
});

it('enforces stage rules: forward-only, rejection needs a reason, terminal is final', function (): void {
    $application = EmployerJobApplication::factory()->for($this->tenant)->graded()->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    Sanctum::actingAs($this->recruiter);
    $base = "/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/applications/{$application->id}/stage";

    // Backward move refused.
    $this->postJson($base, ['stage' => 'applied'])->assertUnprocessable();
    // Rejection without a reason refused.
    $this->postJson($base, ['stage' => 'rejected'])->assertUnprocessable();
    // Reject with reason works and writes the timeline + reason.
    $this->postJson($base, ['stage' => 'rejected', 'note' => 'Experience below the 2-year minimum for this role.'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'rejected');
    // Terminal: no further moves.
    $this->postJson($base, ['stage' => 'shortlisted'])->assertUnprocessable();

    expect($application->fresh()->transitions()->count())->toBe(1);
});

it('records a grade from a completed mock and promotes to graded', function (): void {
    $application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    $blueprint = MockBlueprint::factory()->for($this->tenant)->create();
    $interview = MockInterview::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'status' => 'completed',
        'overall_score' => 82,
        'scorecard' => ['technical_depth' => 80],
        'scorecard_source' => 'ai',
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);

    $updated = app(RecordApplicationGrade::class)->handle($application, $interview);

    expect($updated->stage)->toBe(EmployerApplicationStage::Graded)
        ->and($updated->mock_score)->toBe(82)
        ->and($updated->mock_interview_id)->toBe($interview->id);

    // A worse later attempt never downgrades the score.
    $worse = MockInterview::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'status' => 'completed',
        'overall_score' => 60,
        'scorecard' => [],
        'scorecard_source' => 'ai',
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    expect(app(RecordApplicationGrade::class)->handle($updated, $worse)->mock_score)->toBe(82);
});

it('scopes candidate application lists and denies cross-tenant reads', function (): void {
    $mine = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);
    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => User::factory()->for($this->tenant)->create()->id,
    ]);

    Sanctum::actingAs($this->candidate);
    $response = $this->getJson('/api/v1/me/employer-jobs/applications')->assertOk();
    expect($response->json('data.*.id'))->toBe([$mine->id]);

    // Cross-tenant employer cannot read this job's applications.
    $foreignTenant = Tenant::factory()->create();
    $foreignRecruiter = User::factory()->for($foreignTenant)->create(['user_type' => 'employer']);
    $foreignWorkspace = EmployerWorkspace::factory()->for($foreignTenant)->create();
    EmployerMember::factory()->for($foreignTenant)->owner()->create([
        'employer_workspace_id' => $foreignWorkspace->id,
        'user_id' => $foreignRecruiter->id,
    ]);

    Sanctum::actingAs($foreignRecruiter);
    $this->getJson("/api/v1/employer/workspaces/{$foreignWorkspace->id}/jobs/{$this->job->id}/applications")
        ->assertNotFound();
});
