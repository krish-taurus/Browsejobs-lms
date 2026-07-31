<?php

declare(strict_types=1);

use App\Enums\EmployerRole;
use App\Models\AuditLog;
use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Employers\MockDesign;
use App\Support\Roles\RoleTaxonomy;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
        'role' => EmployerRole::Recruiter->value,
    ]);
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
        'title' => 'Data Engineer',
        'skills' => ['python', 'sql'],
    ]);
    $this->url = "/api/v1/employer/workspaces/{$this->workspace->id}/jobs/{$this->job->id}/mock/design";
});

it('normalises weights to 100 so an employer can express a ratio', function (): void {
    $design = MockDesign::fromArray([
        'competency_weights' => ['communication' => 3, 'technical_depth' => 1],
    ], app(RoleTaxonomy::class));

    // Forcing the form to sum to 100 is arithmetic homework; a ratio is
    // what people actually mean.
    expect($design->normalisedCompetencies())->toBe(['communication' => 75, 'technical_depth' => 25]);
});

it('drops vocabulary the taxonomy does not know', function (): void {
    $design = MockDesign::fromArray([
        'competency_weights' => ['communication' => 50, 'astrology' => 50],
        'format_mix' => ['mcq' => 40, 'telepathy' => 60],
    ], app(RoleTaxonomy::class));

    // An unknown key would reach the grading prompt as an instruction
    // nobody wrote.
    expect(array_keys($design->competencyWeights))->toBe(['communication'])
        ->and(array_keys($design->formatMix))->toBe(['mcq']);
});

it('produces no prompt instruction when nothing was designed', function (): void {
    $design = MockDesign::fromArray(null, app(RoleTaxonomy::class));

    expect($design->isEmpty())->toBeTrue()
        ->and($design->toPromptInstruction())->toBe('');
});

it('turns a design into an instruction the generator can follow', function (): void {
    $design = MockDesign::fromArray([
        'focus_skills' => ['SQL', 'sql', 'Airflow'],
        'competency_weights' => ['communication' => 1, 'technical_depth' => 1],
        'question_count' => 8,
    ], app(RoleTaxonomy::class));

    $text = $design->toPromptInstruction();

    expect($design->focusSkills)->toBe(['sql', 'airflow'])
        ->and($text)->toContain('sql, airflow')
        ->and($text)->toContain('communication 50%')
        ->and($text)->toContain('8 questions');
});

it('saves a design and says a regenerate is needed', function (): void {
    Sanctum::actingAs($this->recruiter);

    $response = $this->putJson($this->url, [
        'focus_skills' => ['sql'],
        'competency_weights' => ['communication' => 60, 'technical_depth' => 40],
        'format_mix' => ['mcq' => 30, 'scenario' => 70],
        'question_count' => 6,
    ])->assertOk();

    $response->assertJsonPath('data.normalised.competencies.communication', 60);

    expect($this->job->fresh()->mock_design['focus_skills'])->toBe(['sql']);

    expect(AuditLog::withoutGlobalScopes()
        ->where('action', 'employer.mock_design_updated')->exists())->toBeTrue();
});

it('never regenerates on save, because candidates may be mid-interview', function (): void {
    Sanctum::actingAs($this->recruiter);

    $before = $this->job->currentMock()?->id;
    $this->putJson($this->url, ['competency_weights' => ['communication' => 100]])->assertOk();

    expect($this->job->fresh()->currentMock()?->id)->toBe($before);
});

it('offers the JD skills first, then the family vocabulary', function (): void {
    Sanctum::actingAs($this->recruiter);

    $skills = $this->getJson($this->url)->assertOk()->json('data.selectable_skills');

    expect(array_slice($skills, 0, 2))->toBe(['python', 'sql'])
        ->and($skills)->toContain('airflow');
});

it('denies a hiring manager designing the interview', function (): void {
    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $manager->id,
        'role' => EmployerRole::HiringManager->value,
    ]);

    Sanctum::actingAs($manager);
    $this->putJson($this->url, ['question_count' => 6])->assertForbidden();
});

it('never leaks or writes across tenants', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWs = EmployerWorkspace::factory()->for($otherTenant)->create();
    $foreign = EmployerJob::factory()->for($otherTenant)->published()->create([
        'employer_workspace_id' => $otherWs->id,
    ]);

    Sanctum::actingAs($this->recruiter);

    $this->getJson("/api/v1/employer/workspaces/{$otherWs->id}/jobs/{$foreign->id}/mock/design")
        ->assertNotFound();
    $this->putJson("/api/v1/employer/workspaces/{$otherWs->id}/jobs/{$foreign->id}/mock/design", [
        'question_count' => 6,
    ])->assertNotFound();

    expect($foreign->fresh()->mock_design)->toBeNull();
});

it('requires authentication', function (): void {
    $this->getJson($this->url)->assertUnauthorized();
});
