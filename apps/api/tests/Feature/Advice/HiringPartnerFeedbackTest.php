<?php

declare(strict_types=1);

use App\Models\HiringPartnerFeedback;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
});

function placementStaff(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('placement-officer');

    return $user;
}

it('requests feedback for an application and returns a public link', function () {
    $application = withinTenant($this->tenant, function () {
        $posting = JobPosting::factory()->for($this->tenant)->create(['company' => 'Acme', 'title' => 'Data Engineer']);

        return JobApplication::factory()->for($this->tenant)->create(['job_posting_id' => $posting->id]);
    });
    Sanctum::actingAs(placementStaff($this->tenant));

    postJson('/api/v1/admin/partner-feedback', ['job_application_id' => $application->id])
        ->assertCreated()
        ->assertJsonPath('data.link', fn ($l) => str_contains((string) $l, '/partner-feedback/'));

    expect(HiringPartnerFeedback::withoutGlobalScopes()->where('company', 'Acme')->exists())->toBeTrue();
});

it('lets a company submit feedback via the public token, once', function () {
    $feedback = withinTenant($this->tenant, fn () => HiringPartnerFeedback::factory()->for($this->tenant)->create());

    getJson("/api/v1/partner-feedback/{$feedback->token}")->assertOk()
        ->assertJsonPath('data.submitted', false)
        ->assertJsonPath('data.role_title', 'Data Engineer');

    $payload = [
        'ratings' => ['technical' => 4, 'problem_solving' => 3, 'communication' => 5, 'culture_fit' => 4],
        'overall' => 4, 'would_hire' => true,
        'strengths' => 'Strong SQL.', 'gaps' => 'Weaker on system design.',
    ];
    postJson("/api/v1/partner-feedback/{$feedback->token}", $payload)->assertOk()
        ->assertJsonPath('data.submitted', true);

    expect($feedback->fresh()->status)->toBe('submitted')
        ->and($feedback->fresh()->gaps)->toBe('Weaker on system design.');

    // Single-use: a second submission is refused.
    postJson("/api/v1/partner-feedback/{$feedback->token}", $payload)->assertStatus(422);
});

it('404s an unknown token', function () {
    getJson('/api/v1/partner-feedback/nope')->assertNotFound();
});

it('gates the admin surface behind the placement ability', function () {
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    getJson('/api/v1/admin/partner-feedback')->assertForbidden();
});

it('does not leak feedback across tenants', function () {
    $other = Tenant::factory()->create();
    HiringPartnerFeedback::factory()->for($other)->create(['company' => 'OtherCo']);

    Sanctum::actingAs(placementStaff($this->tenant));
    getJson('/api/v1/admin/partner-feedback')->assertOk()->assertJsonPath('data', []);
});
