<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\PlacementStory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->workspace = EmployerWorkspace::factory()->for($this->tenant)->create();
    $this->job = EmployerJob::factory()->for($this->tenant)->published()->create([
        'employer_workspace_id' => $this->workspace->id,
    ]);
    $this->course = Course::factory()->for($this->tenant)->create();
});

function gradedMock(User $candidate, EmployerJob $job, int $score, int $daysAgo): void
{
    $blueprint = MockBlueprint::query()->firstOrCreate(
        ['tenant_id' => $job->tenant_id, 'employer_job_id' => $job->id],
        [
            'role_title' => $job->title,
            'competencies' => ['python'],
            'opening_question' => 'Tell me about yourself.',
            'is_active' => false,
        ],
    );

    MockInterview::query()->create([
        'tenant_id' => $candidate->tenant_id,
        'user_id' => $candidate->id,
        'mock_blueprint_id' => $blueprint->id,
        'mode' => MockInterview::MODE_TEXT,
        'status' => MockInterview::STATUS_COMPLETED,
        'overall_score' => $score,
        'started_at' => now()->subDays($daysAgo),
        'completed_at' => now()->subDays($daysAgo),
    ]);
}

it('returns an AI nudge when the model answers in the agreed shape', function (): void {
    $fake = new FakeAiClient;
    $fake->reply = json_encode([
        'headline' => 'Your score moved 23 points',
        'body' => 'You went from 58 to 81, and the median at offer is 79.',
        'cta_label' => 'Browse open roles',
        'cta_kind' => 'browse',
    ], JSON_THROW_ON_ERROR);
    app()->instance(AiClient::class, $fake);

    gradedMock($this->candidate, $this->job, 58, 20);
    gradedMock($this->candidate, $this->job, 81, 2);

    Sanctum::actingAs($this->candidate);

    $this->getJson('/api/v1/me/candidate-dashboard')
        ->assertOk()
        ->assertJsonPath('data.momentum.nudge.source', 'ai')
        ->assertJsonPath('data.momentum.nudge.cta_kind', 'browse')
        ->assertJsonPath('data.momentum.nudge.headline', 'Your score moved 23 points');
});

it('falls back to measured copy when the model returns something unusable', function (): void {
    $fake = new FakeAiClient;
    $fake->reply = 'not json at all';
    app()->instance(AiClient::class, $fake);

    gradedMock($this->candidate, $this->job, 58, 20);
    gradedMock($this->candidate, $this->job, 81, 2);

    Sanctum::actingAs($this->candidate);

    $nudge = $this->getJson('/api/v1/me/candidate-dashboard')
        ->assertOk()
        ->json('data.momentum.nudge');

    // A malformed model reply must never surface as an empty panel or a 500,
    // and the fallback still only states measured numbers.
    expect($nudge['source'])->toBe('fallback')
        ->and($nudge['headline'])->toBe('Your score is up 23 points');
});

it('refuses an AI nudge whose cta_kind is not one we offer', function (): void {
    $fake = new FakeAiClient;
    $fake->reply = json_encode([
        'headline' => 'Buy now',
        'body' => 'Limited spots remaining.',
        'cta_label' => 'Buy',
        'cta_kind' => 'wire_me_money',
    ], JSON_THROW_ON_ERROR);
    app()->instance(AiClient::class, $fake);

    gradedMock($this->candidate, $this->job, 70, 3);

    Sanctum::actingAs($this->candidate);

    $this->getJson('/api/v1/me/candidate-dashboard')
        ->assertOk()
        ->assertJsonPath('data.momentum.nudge.source', 'fallback');
});

it('shows only consented, published, non-sample placement stories', function (): void {
    PlacementStory::query()->create([
        'tenant_id' => $this->tenant->id,
        'course_id' => $this->course->id,
        'student_name' => 'Real Person',
        'before_label' => 'Support engineer',
        'after_role' => 'Data Engineer',
        'company_name' => 'Northwind',
        'consent' => true,
        'is_published' => true,
        'is_sample' => false,
        'position' => 1,
    ]);

    // Each of these must be excluded, for a different reason.
    PlacementStory::query()->create([
        'tenant_id' => $this->tenant->id,
        'course_id' => $this->course->id,
        'student_name' => 'Demo Row',
        'before_label' => 'Fresher',
        'after_role' => 'Analyst',
        'consent' => true,
        'is_published' => true,
        'is_sample' => true, // seeded demo data — never shown as an outcome
        'position' => 2,
    ]);
    PlacementStory::query()->create([
        'tenant_id' => $this->tenant->id,
        'course_id' => $this->course->id,
        'student_name' => 'No Consent',
        'before_label' => 'Fresher',
        'after_role' => 'Analyst',
        'consent' => false,
        'is_published' => true,
        'is_sample' => false,
        'position' => 3,
    ]);
    PlacementStory::query()->create([
        'tenant_id' => $this->tenant->id,
        'course_id' => $this->course->id,
        'student_name' => 'Unpublished',
        'before_label' => 'Fresher',
        'after_role' => 'Analyst',
        'consent' => true,
        'is_published' => false,
        'is_sample' => false,
        'position' => 4,
    ]);

    Sanctum::actingAs($this->candidate);

    $stories = $this->getJson('/api/v1/me/candidate-dashboard')
        ->assertOk()
        ->json('data.momentum.stories');

    expect($stories)->toHaveCount(1)
        ->and($stories[0]['name'])->toBe('Real Person');
});

it('never leaks another tenant placement stories', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherCourse = Course::factory()->for($otherTenant)->create();
    PlacementStory::query()->create([
        'tenant_id' => $otherTenant->id,
        'course_id' => $otherCourse->id,
        'student_name' => 'Someone Elsewhere',
        'before_label' => 'Fresher',
        'after_role' => 'Data Engineer',
        'consent' => true,
        'is_published' => true,
        'is_sample' => false,
        'position' => 1,
    ]);

    Sanctum::actingAs($this->candidate);

    expect($this->getJson('/api/v1/me/candidate-dashboard')->json('data.momentum.stories'))
        ->toHaveCount(0);
});

it('tells a candidate with no graded mock what to do first', function (): void {
    $fake = new FakeAiClient;
    $fake->reply = 'not json';
    app()->instance(AiClient::class, $fake);

    Sanctum::actingAs($this->candidate);

    $this->getJson('/api/v1/me/candidate-dashboard')
        ->assertOk()
        ->assertJsonPath('data.momentum.nudge.cta_kind', 'mock')
        ->assertJsonPath('data.momentum.nudge.headline', 'No graded mock yet');
});
