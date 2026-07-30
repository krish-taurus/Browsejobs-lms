<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\CvProfile;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\StudentScore;
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
        'skills' => ['python', 'sql', 'airflow', 'aws'],
    ]);
});

/** A trained student with a built CV, mastery record and readiness index. */
function trainedStudent(Tenant $tenant, array $skills, int $pri = 0): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);

    CvProfile::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'data' => ['skills' => $skills, 'summary' => 'Trained on the BrowseJobs programme.'],
    ]);

    if ($pri > 0) {
        StudentScore::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'pri' => $pri,
        ]);
    }

    $course = Course::factory()->for($tenant)->create(['name' => 'Data Engineering']);
    $batch = Batch::factory()->for($tenant)->create([
        'course_id' => $course->id,
        'type' => BatchType::Paid->value,
    ]);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id,
        'user_id' => $student->id,
        'status' => BatchMemberStatus::Completed->value,
    ]);

    return $student;
}

it('surfaces trained students ranked by match, with the skill gap named', function (): void {
    $strong = trainedStudent($this->tenant, ['python', 'sql', 'airflow', 'aws', 'dbt'], 82);
    $partial = trainedStudent($this->tenant, ['python', 'sql'], 55);
    trainedStudent($this->tenant, ['figma', 'illustrator'], 90); // no overlap — excluded

    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/talent-pool")
        ->assertOk();

    $ids = $response->json('data.*.candidate_id');
    expect($ids)->toBe([$strong->id, $partial->id]);

    $response->assertJsonPath('data.0.skill_match_pct', 100)
        ->assertJsonPath('data.0.missing_skills', [])
        ->assertJsonPath('data.0.readiness_index', 82)
        ->assertJsonPath('data.0.cv_ready', true)
        ->assertJsonPath('data.0.training.course', 'Data Engineering')
        ->assertJsonPath('data.0.training.status', 'completed')
        ->assertJsonPath('data.1.skill_match_pct', 50);

    // The gap is explicit, so the employer sees what is missing.
    expect($response->json('data.1.missing_skills'))->toBe(['airflow', 'aws']);

    // Candidate contact details are never exposed in the pool.
    expect($response->json('data.0'))->not->toHaveKey('email');
});

it('excludes students who already applied', function (): void {
    $student = trainedStudent($this->tenant, ['python', 'sql', 'airflow', 'aws'], 70);

    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $this->job->id,
        'candidate_id' => $student->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/talent-pool")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('never leaks students from another tenant', function (): void {
    trainedStudent($this->tenant, ['python', 'sql'], 60);
    $otherTenant = Tenant::factory()->create();
    trainedStudent($otherTenant, ['python', 'sql', 'airflow', 'aws'], 95);

    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/talent-pool")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('invites a matched student without creating an application', function (): void {
    $student = trainedStudent($this->tenant, ['python', 'sql', 'airflow'], 75);

    Sanctum::actingAs($this->recruiter);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/talent-pool/{$student->id}/invite")
        ->assertOk()
        ->assertJsonPath('ok', true);

    // The invitation is a nudge — the candidate still chooses to apply.
    expect(EmployerJobApplication::withoutGlobalScopes()
        ->where('employer_job_id', $this->job->id)
        ->where('candidate_id', $student->id)
        ->exists())->toBeFalse();

    expect(AuditLog::withoutGlobalScopes()
        ->where('action', 'employer.student_invited_to_apply')
        ->exists())->toBeTrue();
});

it('returns dashboard chart aggregates', function (): void {
    foreach ([91, 84, 78, 62] as $score) {
        EmployerJobApplication::factory()->for($this->tenant)->graded($score)->create([
            'employer_job_id' => $this->job->id,
            'candidate_id' => User::factory()->for($this->tenant)->create()->id,
        ]);
    }

    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/dashboard")->assertOk();

    // Funnel is cumulative: everyone graded also counts as applied.
    expect($response->json('data.funnel.0.count'))->toBe(4)
        ->and($response->json('data.funnel.0.stage'))->toBe('applied')
        ->and($response->json('data.trend'))->toHaveCount(14);

    $distribution = collect($response->json('data.score_distribution'));
    expect($distribution)->toHaveCount(10)
        ->and($distribution->firstWhere('floor', 90)['count'])->toBe(1)
        ->and($distribution->firstWhere('floor', 60)['count'])->toBe(1);
});
