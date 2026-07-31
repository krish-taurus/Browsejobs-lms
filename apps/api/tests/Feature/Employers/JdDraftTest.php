<?php

declare(strict_types=1);

use App\Enums\EmployerRole;
use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->recruiter = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Acme']);
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $this->recruiter->id,
        'role' => EmployerRole::Recruiter->value,
    ]);
});

function fakeDraft(array $payload): void
{
    $fake = new FakeAiClient;
    $fake->reply = json_encode($payload, JSON_THROW_ON_ERROR);
    app()->instance(AiClient::class, $fake);
}

function draftUrl(int $ws): string
{
    return "/api/v1/employer/workspaces/{$ws}/jd-draft";
}

it('drafts a description and normalised skills from a title', function (): void {
    fakeDraft([
        'description' => 'You will own the ingestion layer feeding our reporting warehouse.',
        'skills' => ['SQL', 'Airflow', ' sql ', 'dbt'],
        'role_family' => 'data',
        'responsibilities' => ['Own ingestion', 'Keep tests honest'],
        'must_haves' => ['Production dbt'],
    ]);

    Sanctum::actingAs($this->recruiter);

    $response = $this->postJson(draftUrl($this->workspace->id), ['title' => 'Data Engineer'])->assertOk();

    // Lowercased and de-duplicated so they match the strings the mock and
    // the talent matcher already use.
    expect($response->json('data.skills'))->toBe(['sql', 'airflow', 'dbt'])
        ->and($response->json('data.role_family'))->toBe('data')
        ->and($response->json('data.description'))->toContain('ingestion layer');
});

it('never creates or publishes a job — it only returns a draft', function (): void {
    fakeDraft(['description' => 'A description.', 'skills' => ['sql']]);

    Sanctum::actingAs($this->recruiter);
    $this->postJson(draftUrl($this->workspace->id), ['title' => 'Data Engineer'])->assertOk();

    expect(EmployerJob::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses an unusable model reply instead of publishing nonsense', function (): void {
    fakeDraft(['skills' => ['sql']]); // no description

    Sanctum::actingAs($this->recruiter);

    $this->postJson(draftUrl($this->workspace->id), ['title' => 'Data Engineer'])->assertStatus(422);
});

it('tells the employer to write it themselves when the model is unreachable', function (): void {
    $fake = new FakeAiClient;
    $fake->reply = 'not json at all';
    app()->instance(AiClient::class, $fake);

    Sanctum::actingAs($this->recruiter);

    $this->postJson(draftUrl($this->workspace->id), ['title' => 'Data Engineer'])
        ->assertStatus(422)
        ->assertJsonFragment(['title' => ['Could not draft that description. Try a more specific job title, or write it yourself.']]);
});

it('requires a title', function (): void {
    fakeDraft(['description' => 'x', 'skills' => []]);
    Sanctum::actingAs($this->recruiter);

    $this->postJson(draftUrl($this->workspace->id), ['title' => '  '])->assertStatus(422);
});

it('denies a hiring manager, who does not post roles', function (): void {
    fakeDraft(['description' => 'x', 'skills' => []]);

    $manager = User::factory()->for($this->tenant)->create(['user_type' => 'employer']);
    EmployerMember::factory()->for($this->tenant)->create([
        'employer_workspace_id' => $this->workspace->id,
        'user_id' => $manager->id,
        'role' => EmployerRole::HiringManager->value,
    ]);

    Sanctum::actingAs($manager);
    $this->postJson(draftUrl($this->workspace->id), ['title' => 'Data Engineer'])->assertForbidden();
});

it('denies drafting into another tenant workspace', function (): void {
    fakeDraft(['description' => 'x', 'skills' => []]);

    $otherTenant = Tenant::factory()->create();
    $foreign = EmployerWorkspace::factory()->for($otherTenant)->create();

    Sanctum::actingAs($this->recruiter);
    $this->postJson(draftUrl($foreign->id), ['title' => 'Data Engineer'])->assertNotFound();
});

it('requires authentication', function (): void {
    $this->postJson(draftUrl($this->workspace->id), ['title' => 'Data Engineer'])->assertUnauthorized();
});
