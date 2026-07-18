<?php

declare(strict_types=1);

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Advice\AdviceGraph;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

/** A cohort member: name is deliberately distinctive so we can prove it never leaks. */
function cohortStudent(Tenant $tenant, MockBlueprint $blueprint, int $mocks, bool $placed): User
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student', 'name' => 'SECRET-'.fake()->unique()->numerify('######')]);

    withinTenant($tenant, function () use ($tenant, $student, $blueprint, $mocks, $placed) {
        $posting = JobPosting::factory()->for($tenant)->create();
        JobApplication::factory()->for($tenant)->create([
            'job_posting_id' => $posting->id, 'user_id' => $student->id,
            'status' => $placed ? JobApplication::STATUS_PLACED : JobApplication::STATUS_APPLIED,
        ]);
        for ($i = 0; $i < $mocks; $i++) {
            MockInterview::query()->create([
                'tenant_id' => $tenant->id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
                'mode' => 'text', 'status' => MockInterview::STATUS_COMPLETED, 'overall_score' => 70,
                'started_at' => now()->subDay(), 'completed_at' => now()->subDay(),
            ]);
        }
    });

    return $student;
}

/** A cohort where practising mocks clearly lifts the placement rate. */
function strongCohort(Tenant $tenant): MockBlueprint
{
    $blueprint = withinTenant($tenant, fn () => MockBlueprint::factory()->create(['tenant_id' => $tenant->id]));

    foreach (range(1, 5) as $i) {                        // high-mock group: 4/5 placed
        cohortStudent($tenant, $blueprint, mocks: 3, placed: $i <= 4);
    }
    foreach (range(1, 5) as $i) {                        // low-mock group: 1/5 placed
        cohortStudent($tenant, $blueprint, mocks: 0, placed: $i <= 1);
    }

    return $blueprint;
}

it('emits an evidence-backed claim once both groups clear the k-anonymity threshold', function () {
    strongCohort($this->tenant);
    $viewer = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $insights = withinTenant($this->tenant, fn () => app(AdviceGraph::class)->insightsFor($viewer));

    expect($insights)->not->toBeEmpty()
        ->and($insights[0])->toContain('mock interviews');
});

it('suppresses the claim when a compared group is below the cohort minimum', function () {
    $blueprint = withinTenant($this->tenant, fn () => MockBlueprint::factory()->create(['tenant_id' => $this->tenant->id]));
    // Only 3 high-mock students — under MIN_COHORT (5) → nothing may be said.
    foreach (range(1, 3) as $i) {
        cohortStudent($this->tenant, $blueprint, mocks: 3, placed: true);
    }
    foreach (range(1, 6) as $i) {
        cohortStudent($this->tenant, $blueprint, mocks: 0, placed: false);
    }
    $viewer = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    expect(withinTenant($this->tenant, fn () => app(AdviceGraph::class)->insightsFor($viewer)))->toBe([]);
});

it('never leaks an individual student into a claim', function () {
    strongCohort($this->tenant);
    $viewer = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    $insights = withinTenant($this->tenant, fn () => app(AdviceGraph::class)->insightsFor($viewer));

    // Aggregate numbers only — no cohort member's name, and only a ratio + generic copy.
    foreach ($insights as $claim) {
        expect($claim)->not->toContain('SECRET-');
        expect($claim)->toMatch('/\d+(\.\d+)?×/');
    }
});

it('does not mix cohorts across tenants', function () {
    strongCohort($this->tenant);           // tenant A has a strong signal
    $other = Tenant::factory()->create();  // tenant B has no cohort at all
    $otherStudent = User::factory()->for($other)->create(['user_type' => 'student']);

    $insights = withinTenant($other, fn () => app(AdviceGraph::class)->insightsFor($otherStudent));

    expect($insights)->toBe([]); // B sees nothing from A's data
});

it('surfaces insights on the coach panel without exposing other students', function () {
    strongCohort($this->tenant);
    $viewer = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($viewer);

    $body = getJson('/api/v1/me/coach')->assertOk()->json('data.insights');

    expect($body)->toBeArray()->not->toBeEmpty();
    expect(json_encode($body))->not->toContain('SECRET-');
});
