<?php

declare(strict_types=1);

use App\Enums\EmployerApplicationStage;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['sanctum.stateful' => ['acme-emp.test']]);
    $this->tenant = Tenant::factory()->domain('acme-emp.test')->create();
    $this->owner = User::factory()->for($this->tenant)->create([
        'user_type' => 'employer',
        'password' => Hash::make('secret-pass-1'),
    ]);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->owner()->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
    ]);
});

it('signs an employer in and refuses non-employer accounts', function (): void {
    $this->withHeader('Origin', 'http://acme-emp.test')
        ->postJson('http://acme-emp.test/api/v1/auth/employer/login', [
            'email' => $this->owner->email,
            'password' => 'secret-pass-1',
        ])->assertOk()->assertJsonPath('status', 'authenticated');

    $student = User::factory()->for($this->tenant)->create([
        'user_type' => 'student',
        'password' => Hash::make('secret-pass-2'),
    ]);

    $this->withHeader('Origin', 'http://acme-emp.test')
        ->postJson('http://acme-emp.test/api/v1/auth/employer/login', [
            'email' => $student->email,
            'password' => 'secret-pass-2',
        ])->assertUnprocessable();
});

it('returns dashboard aggregates for members only', function (): void {
    $job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'created_by_id' => $this->owner->id,
    ]);
    EmployerJobApplication::factory()->for($this->tenant)->graded(80)->create([
        'employer_job_id' => $job->id,
        'candidate_id' => User::factory()->for($this->tenant)->create()->id,
    ]);
    EmployerJobApplication::factory()->for($this->tenant)->create([
        'employer_job_id' => $job->id,
        'candidate_id' => User::factory()->for($this->tenant)->create()->id,
    ]);

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/dashboard")
        ->assertOk()
        ->assertJsonPath('data.active_jobs', 1)
        ->assertJsonPath('data.total_applications', 2)
        ->assertJsonPath('data.graded_applications', 1)
        ->assertJsonPath('data.awaiting_review', 1)
        ->assertJsonPath('data.pipeline.0.id', $job->id)
        ->assertJsonPath('data.pipeline.0.awaiting_review', 1)
        ->assertJsonPath('data.pipeline.0.stage_counts.'.EmployerApplicationStage::Graded->value, 1);

    // Non-member cannot read the dashboard.
    $outsider = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    Sanctum::actingAs($outsider);
    $this->getJson("/api/v1/employer/workspaces/{$this->workspace->id}/dashboard")->assertForbidden();
});
