<?php

declare(strict_types=1);

use App\Actions\Employers\RecordApplicationGrade;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewGraded;
use App\Models\EmployerAutomationRule;
use App\Models\EmployerAutomationRun;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
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

function gradeVia(Tenant $tenant, User $candidate, EmployerJobApplication $application, int $score): void
{
    $blueprint = MockBlueprint::factory()->for($tenant)->create();
    $interview = MockInterview::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'status' => 'completed',
        'overall_score' => $score,
        'scorecard' => [],
        'scorecard_source' => 'ai',
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);

    app(RecordApplicationGrade::class)->handle($application, $interview);
}

it('auto-shortlists at threshold with rule attribution, and skips below it', function (): void {
    EmployerAutomationRule::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'created_by_id' => $this->recruiter->id,
        'min_score' => 75,
    ]);

    $application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    // Below threshold: stays graded, run recorded as skipped.
    gradeVia($this->tenant, $this->candidate, $application, 60);
    expect($application->fresh()->stage)->toBe(EmployerApplicationStage::Graded);
    expect(EmployerAutomationRun::withoutGlobalScopes()->where('outcome', 'skipped_below_threshold')->count())->toBe(1);

    // Above threshold (better attempt): advanced by the rule.
    gradeVia($this->tenant, $this->candidate, $application->fresh(), 85);
    $application = $application->fresh();
    expect($application->stage)->toBe(EmployerApplicationStage::Shortlisted);

    $ruleTransition = $application->transitions()->where('actor_type', 'rule')->first();
    expect($ruleTransition)->not->toBeNull()
        ->and($ruleTransition->to_stage)->toBe('shortlisted');
});

it('chains L1 graded above threshold into an L2 invite (same-day flow)', function (): void {
    $mock = JdMock::factory()->for($this->tenant)->ready()->create(['employer_job_id' => $this->job->id]);

    EmployerAutomationRule::factory()->for($this->tenant)->interviewGraded('l1', 'l2')->create([
        'employer_job_id' => $this->job->id,
        'created_by_id' => $this->recruiter->id,
        'min_score' => 70,
    ]);

    $application = EmployerJobApplication::factory()->for($this->tenant)->graded()->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
        'jd_mock_id' => $mock->id,
        'stage' => EmployerApplicationStage::L1->value,
    ]);

    $l1 = EmployerInterview::factory()->for($this->tenant)->create([
        'employer_job_application_id' => $application->id,
        'status' => EmployerInterviewStatus::Graded->value,
        'overall_score' => 82,
        'graded_at' => now(),
        'submitted_at' => now(),
    ]);

    EmployerInterviewGraded::dispatch($l1);

    $application = $application->fresh();
    expect($application->stage)->toBe(EmployerApplicationStage::L2);

    // Entering L2 auto-created the next round's invite.
    $l2 = EmployerInterview::withoutGlobalScopes()
        ->where('employer_job_application_id', $application->id)
        ->where('round', 'l2')
        ->first();
    expect($l2)->not->toBeNull()
        ->and($l2->status)->toBe(EmployerInterviewStatus::Invited);
});

it('never allows automation to reject or release offers', function (): void {
    Sanctum::actingAs($this->recruiter);
    $base = "/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/automation-rules";

    // Rejection and offer/hired targets are rejected at validation.
    foreach (['rejected', 'offer', 'hired', 'human_round'] as $illegal) {
        $this->postJson($base, [
            'trigger' => 'application_graded',
            'min_score' => 70,
            'action' => 'advance',
            'target_stage' => $illegal,
        ])->assertUnprocessable();
    }

    // A legal rule creates fine and reports simulation impact.
    EmployerJobApplication::factory()->for($this->tenant)->graded(80)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    $this->postJson($base, [
        'trigger' => 'application_graded',
        'min_score' => 75,
        'action' => 'advance',
        'target_stage' => 'shortlisted',
    ])->assertCreated()->assertJsonPath('data.would_match', 1);
});

it('ignores disabled rules and denies non-pipeline roles', function (): void {
    $rule = EmployerAutomationRule::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'created_by_id' => $this->recruiter->id,
        'enabled' => false,
        'min_score' => 50,
    ]);

    $application = EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $this->candidate->id,
    ]);

    gradeVia($this->tenant, $this->candidate, $application, 90);
    expect($application->fresh()->stage)->toBe(EmployerApplicationStage::Graded)
        ->and(EmployerAutomationRun::withoutGlobalScopes()->count())->toBe(0);

    // Hiring manager cannot create rules.
    $hm = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->hiringManager()->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $hm->id,
    ]);
    Sanctum::actingAs($hm);
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/automation-rules", [
        'trigger' => 'application_graded',
        'min_score' => 70,
        'action' => 'advance',
        'target_stage' => 'shortlisted',
    ])->assertForbidden();

    // Toggle works for the recruiter.
    Sanctum::actingAs($this->recruiter);
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/automation-rules/{$rule->id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.enabled', true);
});
