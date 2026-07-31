<?php

declare(strict_types=1);

use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Roles\RoleTaxonomy;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
    ]);
});

it('covers non-IT hiring, not only engineering', function (): void {
    $sectors = collect(app(RoleTaxonomy::class)->families())->pluck('sector')->unique();

    // A finance or sales employer meeting an empty dropdown types free text
    // that never matches anything downstream.
    expect($sectors)->toContain('it')->toContain('non_it');

    $families = collect(app(RoleTaxonomy::class)->families())->pluck('key');
    expect($families)->toContain('finance')->toContain('sales')->toContain('people');
});

it('resolves a decorated title to its family', function (): void {
    $taxonomy = app(RoleTaxonomy::class);

    expect($taxonomy->familyForTitle('Data Engineer')['key'])->toBe('data')
        ->and($taxonomy->familyForTitle('data engineer')['key'])->toBe('data')
        ->and($taxonomy->familyForTitle('Senior Data Engineer II')['key'])->toBe('data')
        ->and($taxonomy->familyForTitle('Accountant')['key'])->toBe('finance');
});

it('prefers the longest matching title so a substring does not win', function (): void {
    // "Analytics Engineer" contains neither more nor less than "Data
    // Engineer" by luck — the longer known title must win.
    expect(app(RoleTaxonomy::class)->familyForTitle('Senior Analytics Engineer')['key'])->toBe('data');
});

it('returns null for a title it does not know rather than guessing', function (): void {
    expect(app(RoleTaxonomy::class)->familyForTitle('Chief Vibes Officer'))->toBeNull()
        ->and(app(RoleTaxonomy::class)->familyForTitle('  '))->toBeNull();
});

it('suggests core and optional skills for a title', function (): void {
    $skills = app(RoleTaxonomy::class)->skillsForTitle('Data Engineer');

    expect($skills['core'])->toContain('sql')
        ->and($skills['optional'])->toContain('airflow');
});

it('serves the whole vocabulary in one call', function (): void {
    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson('/api/v1/employer/role-taxonomy')->assertOk();

    expect($response->json('data.families'))->not->toBeEmpty()
        ->and($response->json('data.titles'))->not->toBeEmpty()
        ->and($response->json('data.competencies'))->not->toBeEmpty()
        ->and($response->json('data.question_formats'))->not->toBeEmpty()
        ->and($response->json('data.suggested'))->toBeNull();
});

it('returns suggested skills when a title is supplied', function (): void {
    Sanctum::actingAs($this->recruiter);

    $response = $this->getJson('/api/v1/employer/role-taxonomy?title=Sales%20Manager')->assertOk();

    expect($response->json('data.suggested.core'))->toContain('prospecting');
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/employer/role-taxonomy')->assertUnauthorized();
});

it('names every competency the mock designer can weight', function (): void {
    $keys = collect(app(RoleTaxonomy::class)->competencies())->pluck('key');

    expect($keys)->toContain('communication')
        ->toContain('technical_depth')
        ->toContain('interpersonal');
});
