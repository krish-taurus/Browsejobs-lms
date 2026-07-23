<?php

declare(strict_types=1);

use App\Models\CvProfile;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\RealInterviewQuestion;
use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Per-JD interview prep (ADR 0048): potential questions (real bank + JD),
 * the confidence score, and the quick JD mock.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    withinTenant($this->tenant, fn () => CvProfile::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->student->id,
        'data' => ['skills' => ['Python', 'SQL']],
    ]));
});

function prepItem(Tenant $tenant, array $overrides = []): JobFeedItem
{
    return withinTenant($tenant, function () use ($tenant, $overrides) {
        $source = JobFeedSource::factory()->for($tenant)->create();

        return JobFeedItem::factory()->for($tenant)->create(array_merge([
            'job_feed_source_id' => $source->id,
            'title' => 'Data Engineer',
            'role_title' => 'Data Engineer',
            'extracted_skills' => ['python', 'sql', 'spark'],
        ], $overrides));
    });
}

/* ---- Potential questions ---- */

it('serves real-bank questions for the role first, then JD-derived ones, and caches on the item', function () {
    $item = prepItem($this->tenant);
    withinTenant($this->tenant, fn () => RealInterviewQuestion::factory()->for($this->tenant)->create([
        'role_title' => 'Data Engineer',
    ]));
    Sanctum::actingAs($this->student);

    $questions = getJson("/api/v1/me/jobs/{$item->id}/prep")->assertOk()->json('data.questions');

    // Real question leads, labelled as real; JD-derived (AI fallback here) follow.
    expect($questions[0]['source'])->toBe('real')
        ->and(collect($questions)->pluck('source'))->toContain('jd')
        ->and(collect($questions)->pluck('question')->first(fn ($q) => str_contains($q, 'spark')))->not->toBeNull();

    // Cached on the item — the next viewer costs nothing.
    expect($item->fresh()->prep_questions)->not->toBeEmpty();
});

it('404s prep for another tenant\'s posting', function () {
    $other = Tenant::factory()->create();
    $foreign = prepItem($other);
    Sanctum::actingAs($this->student);

    getJson("/api/v1/me/jobs/{$foreign->id}/prep")->assertNotFound();
});

/* ---- Confidence score ---- */

it('blends match, cached PRI and best mock into the confidence score with its basis', function () {
    prepItem($this->tenant, ['extracted_skills' => ['python', 'sql']]); // full skill match
    withinTenant($this->tenant, function (): void {
        StudentScore::factory()->for($this->tenant)->create(['user_id' => $this->student->id, 'pri' => 80]);
        $blueprint = MockBlueprint::factory()->for($this->tenant)->create();
        MockInterview::query()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->student->id,
            'mock_blueprint_id' => $blueprint->id, 'mode' => 'text',
            'status' => MockInterview::STATUS_COMPLETED, 'overall_score' => 60,
            'scorecard' => ['overall' => 60], 'scorecard_source' => 'ai',
            'started_at' => now()->subDay(), 'completed_at' => now()->subDay(),
        ]);
    });
    Sanctum::actingAs($this->student);

    $row = getJson('/api/v1/me/jobs')->assertOk()->json('data.0');

    // 0.5*match + 0.3*80 + 0.2*60 with match ≈ 100 → high but honest.
    expect($row['confidence_pct'])->toBeGreaterThan(70)->toBeLessThanOrEqual(100)
        ->and($row['confidence_based_on'])->toContain('skill match', 'course progress', 'mock performance')
        ->and($row['has_mock_signal'])->toBeTrue();
});

it('reports a match-only basis when no PRI or mock exists yet', function () {
    prepItem($this->tenant);
    Sanctum::actingAs($this->student);

    $row = getJson('/api/v1/me/jobs')->assertOk()->json('data.0');

    expect($row['confidence_based_on'])->toBe(['skill match'])
        ->and($row['has_mock_signal'])->toBeFalse()
        ->and($row['confidence_pct'])->toBe($row['match_pct']);
});

/* ---- Quick JD mock ---- */

it('starts a quick mock scoped to the posting via a hidden blueprint', function () {
    config(['monetization.text_practice_enabled' => true]);
    $item = prepItem($this->tenant, ['company' => 'Acme Analytics']);
    Sanctum::actingAs($this->student);

    $mockId = postJson("/api/v1/me/jobs/{$item->id}/mock")->assertCreated()->json('data.mock_id');

    $interview = MockInterview::withoutGlobalScopes()->findOrFail($mockId);
    $blueprint = MockBlueprint::withoutGlobalScopes()->findOrFail($interview->mock_blueprint_id);

    expect($blueprint->job_feed_item_id)->toBe($item->id)
        ->and($blueprint->is_active)->toBeFalse() // hidden from course pickers
        ->and($blueprint->role_title)->toBe('Data Engineer')
        ->and($blueprint->competencies)->toContain('python', 'spark')
        ->and($blueprint->opening_question)->toContain('Acme Analytics');

    // Starting again resumes the same in-progress mock; the blueprint is reused.
    $again = postJson("/api/v1/me/jobs/{$item->id}/mock")->assertCreated()->json('data.mock_id');
    expect($again)->toBe($mockId)
        ->and(MockBlueprint::withoutGlobalScopes()->where('job_feed_item_id', $item->id)->count())->toBe(1);
});

it('honours the text-practice gate for JD mocks', function () {
    config(['monetization.text_practice_enabled' => false]);
    $item = prepItem($this->tenant);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$item->id}/mock")->assertForbidden();
});

it('404s a JD mock on another tenant\'s posting', function () {
    config(['monetization.text_practice_enabled' => true]);
    $other = Tenant::factory()->create();
    $foreign = prepItem($other);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$foreign->id}/mock")->assertNotFound();
});
