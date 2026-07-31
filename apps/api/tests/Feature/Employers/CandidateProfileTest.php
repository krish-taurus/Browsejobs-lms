<?php

declare(strict_types=1);

use App\Enums\EmployerApplicationStage;
use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\CvProfile;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Verification\VerificationGateway;
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
    ]);
    $this->candidate = User::factory()->for($this->tenant)->create([
        'user_type' => 'student',
        'name' => 'Meera Iyer',
        'email' => 'meera@example.com',
        'phone' => '9000000000',
    ]);
    $this->application = EmployerJobApplication::factory()->for($this->tenant)->graded(84)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);
    $this->url = fn () => "/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/applications/{$this->application->id}/profile";
});

function gradedInterviewFor(User $candidate, EmployerJob $job, array $card): MockInterview
{
    $blueprint = MockBlueprint::query()->firstOrCreate(
        ['tenant_id' => $job->tenant_id, 'employer_job_id' => $job->id],
        ['role_title' => $job->title, 'competencies' => ['python'], 'opening_question' => 'Hi.', 'is_active' => false],
    );

    return MockInterview::query()->create([
        'tenant_id' => $candidate->tenant_id,
        'user_id' => $candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_VOICE,
        'status' => MockInterview::STATUS_COMPLETED,
        'overall_score' => 84,
        'scorecard' => $card,
        'scorecard_source' => 'ai',
        'duration_seconds' => 1180,
        'started_at' => now()->subMinutes(25),
        'completed_at' => now(),
    ]);
}

it('returns the interview breakdown the employer hires on', function (): void {
    $interview = gradedInterviewFor($this->candidate, $this->job, [
        'overall' => 84,
        'competencies' => [
            ['name' => 'Technical depth', 'score' => 88],
            ['name' => 'Communication', 'score' => 79],
        ],
        'strong_moments' => ['Explained an incremental pipeline end to end.'],
        'weak_moments' => ['Vague on cost trade-offs.'],
    ]);
    $this->application->update(['mock_interview_id' => $interview->id]);

    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson(($this->url)())->assertOk();

    $response->assertJsonPath('data.interview.overall', 84)
        ->assertJsonPath('data.interview.competencies.0.name', 'Technical depth')
        ->assertJsonPath('data.interview.competencies.1.score', 79)
        ->assertJsonPath('data.interview.graded_by', 'ai');

    expect($response->json('data.interview.strengths'))->toContain('Explained an incremental pipeline end to end.')
        ->and($response->json('data.interview.concerns'))->toContain('Vague on cost trade-offs.');
});

it('says plainly that proctoring signals are not captured', function (): void {
    $interview = gradedInterviewFor($this->candidate, $this->job, ['overall' => 84]);
    $this->application->update(['mock_interview_id' => $interview->id]);

    Sanctum::actingAs($this->recruiter);

    // Silence here would read as a clean proctoring report we never observed.
    $this->getJson(($this->url)())
        ->assertOk()
        ->assertJsonPath('data.interview.session.proctoring_captured', false)
        ->assertJsonPath('data.interview.session.mode', 'voice');
});

it('hides contact details until the candidate is shortlisted', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->getJson(($this->url)())
        ->assertOk()
        ->assertJsonPath('data.candidate.email', null)
        ->assertJsonPath('data.candidate.contact_visible', false)
        ->assertJsonPath('data.candidate.name', 'Meera Iyer');

    $this->application->update(['stage' => EmployerApplicationStage::Shortlisted->value]);

    $this->getJson(($this->url)())
        ->assertOk()
        ->assertJsonPath('data.candidate.email', 'meera@example.com')
        ->assertJsonPath('data.candidate.contact_visible', true);
});

it('shows verification outcomes but never the record behind them', function (): void {
    $gateway = app(VerificationGateway::class);
    $identity = $gateway->submit($this->candidate, VerificationKind::Identity, ['reference' => 'SECRET-UAN-123']);
    $gateway->settle($identity, VerificationStatus::Verified, null, ['issuer' => 'DigiLocker']);

    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson(($this->url)())->assertOk();

    $identityCheck = collect($response->json('data.verification.checks'))->firstWhere('kind', 'identity');
    expect($identityCheck['status'])->toBe('verified');

    // An employer learns the outcome, not the PF record or the reference.
    $raw = $response->getContent();
    expect($raw)->not->toContain('SECRET-UAN-123')
        ->and($identityCheck)->not->toHaveKey('provider_ref')
        ->and($identityCheck)->not->toHaveKey('evidence');
});

it('reports the badge only when every required check is live', function (): void {
    $gateway = app(VerificationGateway::class);
    $gateway->settle($gateway->submit($this->candidate, VerificationKind::Identity), VerificationStatus::Verified);

    Sanctum::actingAs($this->recruiter);
    $this->getJson(($this->url)())->assertOk()->assertJsonPath('data.verification.badge', false);

    $gateway->settle($gateway->submit($this->candidate, VerificationKind::Employment), VerificationStatus::Verified);
    $this->getJson(($this->url)())->assertOk()->assertJsonPath('data.verification.badge', true);
});

it('includes the parsed CV when one exists and null when it does not', function (): void {
    Sanctum::actingAs($this->recruiter);
    $this->getJson(($this->url)())->assertOk()->assertJsonPath('data.cv', null);

    CvProfile::query()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->candidate->id,
        'data' => ['summary' => 'Analytics engineer.', 'skills' => ['sql', 'dbt']],
    ]);

    $this->getJson(($this->url)())
        ->assertOk()
        ->assertJsonPath('data.cv.summary', 'Analytics engineer.')
        ->assertJsonPath('data.cv.skills.1', 'dbt');
});

it('returns a null interview when the candidate has not sat one', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->getJson(($this->url)())->assertOk()->assertJsonPath('data.interview', null);
});

it('refuses an application belonging to another job', function (): void {
    $otherJob = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$otherJob->id}/applications/{$this->application->id}/profile")
        ->assertNotFound();
});

it('never opens a candidate from another tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWs = EmployerWorkspace::factory()->for($otherTenant)->create();
    $otherJob = EmployerJob::factory()->for($otherTenant)->published()->create([
        'employer_workspace_id' => $otherWs->id,
    ]);
    $foreign = EmployerJobApplication::factory()->for($otherTenant)->create([
        'employer_job_id' => $otherJob->id,
        'candidate_id' => User::factory()->for($otherTenant)->create()->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    $this->getJson("/api/v1/employer/workspaces/{$otherWs->id}/jobs/{$otherJob->id}/applications/{$foreign->id}/profile")
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson(($this->url)())->assertUnauthorized();
});
