<?php

declare(strict_types=1);

use App\Enums\EmployerRole;
use App\Models\AuditLog;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('registers a workspace and enrols the creator as owner', function (): void {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/employer/workspaces', [
        'name' => 'Acme Technologies',
        'website' => 'https://acme.example.com',
        'industry' => 'SaaS',
        'company_size' => '51-200',
        'locations' => ['Bengaluru'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Acme Technologies')
        ->assertJsonPath('data.my_role', 'owner');

    $workspace = EmployerWorkspace::withoutGlobalScopes()->where('name', 'Acme Technologies')->firstOrFail();
    expect($workspace->tenant_id)->toBe($this->tenant->id);

    $member = EmployerMember::withoutGlobalScopes()
        ->where('employer_workspace_id', $workspace->id)
        ->where('user_id', $this->user->id)
        ->firstOrFail();
    expect($member->role)->toBe(EmployerRole::Owner);

    expect($this->user->fresh()->user_type)->toBe('employer');

    expect(AuditLog::withoutGlobalScopes()
        ->where('action', 'employer.workspace_registered')
        ->exists())->toBeTrue();
});

it('lists only workspaces the caller belongs to', function (): void {
    $mine = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->owner()->create([
        'employer_workspace_id' => $mine->id,
        'user_id' => $this->user->id,
    ]);
    EmployerWorkspace::factory()->for($this->tenant)->create(); // same tenant, not a member

    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/employer/workspaces')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('denies a non-member viewing a workspace', function (): void {
    $workspace = EmployerWorkspace::factory()->for($this->tenant)->create();

    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/employer/workspaces/{$workspace->id}")->assertForbidden();
});

it('404s a foreign tenant workspace', function (): void {
    $foreign = EmployerWorkspace::factory()->for(Tenant::factory()->create())->create();

    Sanctum::actingAs($this->user);

    $this->getJson("/api/v1/employer/workspaces/{$foreign->id}")->assertNotFound();
});

it('rejects unauthenticated access', function (): void {
    $this->getJson('/api/v1/employer/workspaces')->assertUnauthorized();
});

it('lets only owners update the workspace', function (): void {
    $workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $workspace->id,
        'user_id' => $this->user->id, // recruiter
    ]);

    Sanctum::actingAs($this->user);
    $this->patchJson("/api/v1/employer/workspaces/{$workspace->id}", ['name' => 'Renamed'])
        ->assertForbidden();

    $owner = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->owner()->create([
        'employer_workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);

    Sanctum::actingAs($owner);
    $this->patchJson("/api/v1/employer/workspaces/{$workspace->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});
