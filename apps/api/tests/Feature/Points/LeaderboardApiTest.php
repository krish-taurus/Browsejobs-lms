<?php

declare(strict_types=1);

use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\PointsEvent;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $course = Course::factory()->for($this->tenant)->create();
    $this->batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id]);

    $this->me = enrolIn($this->tenant, $this->batch, 'Priya Me');
    $this->top = enrolIn($this->tenant, $this->batch, 'Arjun Top');
    $this->shy = enrolIn($this->tenant, $this->batch, 'Shy Student', optOut: true);

    grantPoints($this->tenant, $this->batch, $this->top, 100);
    grantPoints($this->tenant, $this->batch, $this->shy, 60);
    grantPoints($this->tenant, $this->batch, $this->me, 40);
});

function enrolIn(Tenant $tenant, Batch $batch, string $name, bool $optOut = false): User
{
    $user = User::factory()->for($tenant)->create([
        'user_type' => 'student', 'name' => $name, 'leaderboard_opt_out' => $optOut,
    ]);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $user->id, 'status' => 'enrolled',
    ]);

    return $user;
}

function grantPoints(Tenant $tenant, Batch $batch, User $user, int $points, ?string $on = null): void
{
    PointsEvent::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $user->id, 'batch_id' => $batch->id,
        'source' => 'quiz', 'source_key' => 'quiz:'.uniqid(), 'points' => $points,
        'awarded_on' => $on ?? now()->toDateString(),
    ]);
}

it('ranks the batch, names the top ten, and anonymises opt-outs', function () {
    Sanctum::actingAs($this->me);

    $response = $this->getJson('/api/v1/me/leaderboard')->assertOk()->json('data');

    expect($response['top'][0])->toMatchArray(['rank' => 1, 'name' => 'Arjun Top', 'points' => 100, 'is_me' => false])
        ->and($response['top'][1]['name'])->toBe('Student') // opted out
        ->and($response['top'][2])->toMatchArray(['rank' => 3, 'name' => 'Priya Me', 'is_me' => true])
        ->and($response['me'])->toMatchArray(['rank' => 3, 'points' => 40, 'to_next' => 20])
        ->and($response['me']['coach_line'])->toContain('20 points from #2')
        ->and($response['participants'])->toBe(3);
});

it('filters to the current week in weekly view but keeps all-time complete', function () {
    grantPoints($this->tenant, $this->batch, $this->me, 500, now()->subWeeks(2)->toDateString());

    Sanctum::actingAs($this->me);

    $weekly = $this->getJson('/api/v1/me/leaderboard?period=weekly')->json('data');
    $alltime = $this->getJson('/api/v1/me/leaderboard?period=alltime')->json('data');

    expect($weekly['me']['points'])->toBe(40)
        ->and($alltime['me']['points'])->toBe(540)
        ->and($alltime['top'][0]['is_me'])->toBeTrue();
});

it('excludes other batches and tenants from the board', function () {
    $otherTenant = Tenant::factory()->create();
    $otherCourse = Course::factory()->for($otherTenant)->create();
    $otherBatch = Batch::factory()->for($otherTenant)->create(['course_id' => $otherCourse->id]);
    $foreign = enrolIn($otherTenant, $otherBatch, 'Foreign');
    grantPoints($otherTenant, $otherBatch, $foreign, 999);

    Sanctum::actingAs($this->me);
    $response = $this->getJson('/api/v1/me/leaderboard')->json('data');

    expect($response['participants'])->toBe(3)
        ->and(collect($response['top'])->pluck('name'))->not->toContain('Foreign');
});

it('lets a student toggle the leaderboard opt-out', function () {
    Sanctum::actingAs($this->me);

    $this->putJson('/api/v1/me/leaderboard', ['opt_out' => true])
        ->assertOk()
        ->assertJsonPath('data.opt_out', true);

    expect($this->me->fresh()->leaderboard_opt_out)->toBeTrue();

    // Another student now sees them anonymised.
    Sanctum::actingAs($this->top);
    $top = $this->getJson('/api/v1/me/leaderboard')->json('data.top');
    expect(collect($top)->pluck('name'))->not->toContain('Priya Me');
});

it('returns an empty board gracefully for a student with no batch', function () {
    $loner = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($loner);

    $this->getJson('/api/v1/me/leaderboard')
        ->assertOk()
        ->assertJsonPath('data.batch', null)
        ->assertJsonPath('data.me', null);
});
