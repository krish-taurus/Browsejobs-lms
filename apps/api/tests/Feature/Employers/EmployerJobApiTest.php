<?php

declare(strict_types=1);

use App\Enums\JdMockStatus;
use App\Events\EmployerJobPublished;
use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id, // recruiter: managesPipeline
    ]);
});

it('creates a draft JD', function (): void {
    Sanctum::actingAs($this->recruiter);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs", [
        'title' => 'Data Engineer',
        'description' => 'Build and operate our lakehouse pipelines with Python, SQL, Airflow on AWS.',
        'skills' => ['python', 'sql', 'airflow', 'aws'],
        'experience_min_years' => 2,
        'experience_max_years' => 5,
        'ctc_min_paise' => 120000000,
        'ctc_max_paise' => 180000000,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', 'Data Engineer');
});

it('denies a hiring manager creating a JD', function (): void {
    $hm = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->hiringManager()->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $hm->id,
    ]);

    Sanctum::actingAs($hm);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs", [
        'title' => 'X',
        'description' => 'Y',
    ])->assertForbidden();
});

it('publishing creates jd_mock v1 and fires the event', function (): void {
    Event::fake([EmployerJobPublished::class]);

    $job = EmployerJob::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$job->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    Event::assertDispatched(EmployerJobPublished::class);

    $mock = JdMock::withoutGlobalScopes()->where('employer_job_id', $job->id)->firstOrFail();
    expect($mock->version)->toBe(1)
        ->and($mock->status)->toBe(JdMockStatus::Pending);
});

it('rejects invalid lifecycle transitions', function (): void {
    $job = EmployerJob::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    // Draft cannot be paused; closed cannot be republished.
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$job->id}/status", ['status' => 'paused'])
        ->assertUnprocessable();

    $job->update(['status' => 'closed']);
    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$job->id}/publish")
        ->assertUnprocessable();
});

it('404s a JD from another workspace or tenant', function (): void {
    $otherWorkspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    $foreignJob = EmployerJob::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $otherWorkspace->id,
        'created_by_id' => $this->recruiter->id,
    ]);

    $otherTenant = Tenant::factory()->create();
    $crossTenantJob = EmployerJob::factory()->for($otherTenant)->create([
        'employer_workspace_id' => EmployerWorkspace::factory()->for($otherTenant)->create()->id,
        'created_by_id' => User::factory()->for($otherTenant)->create()->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    // Same tenant, different workspace → 404 (cross-workspace denial).
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$foreignJob->id}")
        ->assertNotFound();
    // Different tenant → 404 (cross-tenant denial via TenantScope).
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$crossTenantJob->id}")
        ->assertNotFound();
});
