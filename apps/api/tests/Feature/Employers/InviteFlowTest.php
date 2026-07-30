<?php

declare(strict_types=1);

use App\Enums\EmployerRole;
use App\Events\EmployerMemberInvited;
use App\Models\AuditLog;
use App\Models\EmployerInvite;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    EmployerMember::factory()->for($this->tenant)->owner()->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
    ]);
});

it('lets an owner invite a member and dispatches the event', function (): void {
    Event::fake([EmployerMemberInvited::class]);
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/invites", [
        'email' => 'newhire@example.com',
        'role' => 'recruiter',
    ]);

    $response->assertCreated()->assertJsonPath('data.email', 'newhire@example.com');
    // The raw token must never be serialized in API responses.
    expect($response->json('data'))->not->toHaveKey('token');

    Event::assertDispatched(EmployerMemberInvited::class);

    $invite = EmployerInvite::withoutGlobalScopes()->where('email', 'newhire@example.com')->firstOrFail();
    expect($invite->tenant_id)->toBe($this->tenant->id)
        ->and($invite->isPending())->toBeTrue();
});

it('denies a recruiter inviting members', function (): void {
    $recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $recruiter->id,
    ]);

    Sanctum::actingAs($recruiter);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/invites", [
        'email' => 'x@example.com',
        'role' => 'recruiter',
    ])->assertForbidden();
});

it('rejects a duplicate pending invite', function (): void {
    Sanctum::actingAs($this->owner);

    EmployerInvite::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'invited_by_id' => $this->owner->id,
        'email' => 'dup@example.com',
    ]);

    $this->postJson("/api/v1/employer/workspaces/{$this->workspace->id}/invites", [
        'email' => 'dup@example.com',
        'role' => 'recruiter',
    ])->assertUnprocessable();
});

it('accepts an invite once and only once', function (): void {
    $invitee = User::factory()->for($this->tenant)->create([
        'email' => 'invitee@example.com',
        'user_type' => 'student',
    ]);
    $invite = EmployerInvite::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'invited_by_id' => $this->owner->id,
        'email' => 'invitee@example.com',
        'role' => EmployerRole::HiringManager->value,
    ]);

    Sanctum::actingAs($invitee);

    $this->postJson('/api/v1/employer/invites/accept', ['token' => $invite->token])
        ->assertCreated()
        ->assertJsonPath('data.role', 'hiring_manager');

    expect($invite->fresh()->accepted_at)->not->toBeNull()
        ->and($invitee->fresh()->user_type)->toBe('employer');

    // Single-use: a second accept fails even for the same user.
    $this->postJson('/api/v1/employer/invites/accept', ['token' => $invite->token])
        ->assertUnprocessable();
});

it('rejects expired invites and wrong-email accepts', function (): void {
    $invitee = User::factory()->for($this->tenant)->create(['email' => 'right@example.com']);

    $expired = EmployerInvite::factory()->for($this->tenant)->expired()->create([
        'employer_workspace_id' => $this->workspace->id,
        'invited_by_id' => $this->owner->id,
        'email' => 'right@example.com',
    ]);
    $forOther = EmployerInvite::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'invited_by_id' => $this->owner->id,
        'email' => 'someoneelse@example.com',
    ]);

    Sanctum::actingAs($invitee);

    $this->postJson('/api/v1/employer/invites/accept', ['token' => $expired->token])
        ->assertUnprocessable();
    $this->postJson('/api/v1/employer/invites/accept', ['token' => $forOther->token])
        ->assertUnprocessable();
});

it('rejects a cross-tenant invite token', function (): void {
    $otherTenant = Tenant::factory()->create();
    $foreignInvite = EmployerInvite::factory()->for($otherTenant)->create([
        'employer_workspace_id' => EmployerWorkspace::factory()->for($otherTenant)->create()->id,
        'invited_by_id' => User::factory()->for($otherTenant)->create()->id,
        'email' => 'victim@example.com',
    ]);

    $victim = User::factory()->for($this->tenant)->create(['email' => 'victim@example.com']);
    Sanctum::actingAs($victim);

    $this->postJson('/api/v1/employer/invites/accept', ['token' => $foreignInvite->token])
        ->assertUnprocessable();

    expect(EmployerMember::withoutGlobalScopes()
        ->where('user_id', $victim->id)
        ->exists())->toBeFalse();
});

it('changes a member role with an audit entry and protects the last owner', function (): void {
    $recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $membership = EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $recruiter->id,
    ]);

    Sanctum::actingAs($this->owner);

    $this->patchJson("/api/v1/employer/workspaces/{$this->workspace->id}/members/{$membership->id}", [
        'role' => 'hiring_manager',
    ])->assertOk()->assertJsonPath('data.role', 'hiring_manager');

    expect(AuditLog::withoutGlobalScopes()
        ->where('action', 'employer.member_role_changed')
        ->exists())->toBeTrue();

    // Demoting the sole owner is refused.
    $ownerMembership = EmployerMember::withoutGlobalScopes()
        ->where('employer_workspace_id', $this->workspace->id)
        ->where('user_id', $this->owner->id)
        ->firstOrFail();

    $this->patchJson("/api/v1/employer/workspaces/{$this->workspace->id}/members/{$ownerMembership->id}", [
        'role' => 'recruiter',
    ])->assertUnprocessable();
});

it('removes a member but never the last owner', function (): void {
    $recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $membership = EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $recruiter->id,
    ]);

    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/v1/employer/workspaces/{$this->workspace->id}/members/{$membership->id}")
        ->assertOk();
    expect(EmployerMember::withoutGlobalScopes()->find($membership->id))->toBeNull();

    $ownerMembership = EmployerMember::withoutGlobalScopes()
        ->where('employer_workspace_id', $this->workspace->id)
        ->where('user_id', $this->owner->id)
        ->firstOrFail();

    $this->deleteJson("/api/v1/employer/workspaces/{$this->workspace->id}/members/{$ownerMembership->id}")
        ->assertUnprocessable();
});
