<?php

declare(strict_types=1);

use App\Actions\Store\SettlePurchase;
use App\Actions\Store\StartPurchase;
use App\Enums\ProductKind;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\CvProfile;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Product;
use App\Models\RealInterviewQuestion;
use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Per-JD interview prep + the Interview Kit paywall (ADR 0048): potential
 * questions (real bank + JD), confidence score, quick JD mock, and unlocks.
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

/** Give the student an occupying batch seat — kits are part of the program. */
function jobPrepEnrol(Tenant $tenant, User $student): void
{
    withinTenant($tenant, function () use ($tenant, $student): void {
        $course = Course::factory()->for($tenant)->create();
        $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'type' => 'paid']);
        BatchMember::factory()->for($tenant)->create([
            'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled',
        ]);
    });
}

/* ---- Potential questions + paywall ---- */

it('gives an enrolled student the full paper: real-bank questions first, JD-derived after, cached', function () {
    jobPrepEnrol($this->tenant, $this->student);
    $item = prepItem($this->tenant);
    withinTenant($this->tenant, fn () => RealInterviewQuestion::factory()->for($this->tenant)->create([
        'role_title' => 'Data Engineer',
    ]));
    Sanctum::actingAs($this->student);

    $data = getJson("/api/v1/me/jobs/{$item->id}/prep")->assertOk()->json('data');

    expect($data['unlocked'])->toBeTrue()
        ->and($data['questions'][0]['source'])->toBe('real')
        ->and($data['real_count'])->toBe(1)
        ->and(collect($data['questions'])->pluck('question')->first(fn ($q) => str_contains($q, 'spark')))->not->toBeNull();

    // Cached on the item — the next viewer costs nothing.
    expect($item->fresh()->prep_questions)->not->toBeEmpty();
});

it('shows an outsider only a 3-question sample with honest counts and the kit offers', function () {
    $item = prepItem($this->tenant);
    withinTenant($this->tenant, function (): void {
        Product::factory()->for($this->tenant)->create([
            'sku' => 'job-kit', 'name' => 'Interview Kit · one job', 'feature' => 'job_kit',
            'kind' => ProductKind::Pack->value, 'price_paise' => 10_000, 'grant_amount' => 1,
        ]);
    });
    Sanctum::actingAs($this->student);

    $data = getJson("/api/v1/me/jobs/{$item->id}/prep")->assertOk()->json('data');

    expect($data['unlocked'])->toBeFalse()
        ->and($data['questions'])->toHaveCount(3)
        ->and($data['total'])->toBeGreaterThan(3)
        ->and($data['offers'][0]['sku'])->toBe('job-kit')
        ->and($data['credits'])->toBe(0);
});

it('unlocks a job with a kit credit and 402s with offers when the wallet is empty', function () {
    $item = prepItem($this->tenant);
    withinTenant($this->tenant, function (): void {
        Product::factory()->for($this->tenant)->create([
            'sku' => 'job-kit', 'feature' => 'job_kit', 'kind' => ProductKind::Pack->value,
            'price_paise' => 10_000, 'grant_amount' => 1,
        ]);
    });
    Sanctum::actingAs($this->student);

    // Empty wallet → 402 with the offers.
    postJson("/api/v1/me/jobs/{$item->id}/unlock")->assertStatus(402)
        ->assertJsonPath('error.code', 'no_job_kit_credits')
        ->assertJsonPath('error.offers.0.sku', 'job-kit');

    // With a credit the unlock sticks and the full paper opens.
    app(EntitlementService::class)->grantCredits($this->student, 'job_kit', 1, 'test');
    postJson("/api/v1/me/jobs/{$item->id}/unlock")->assertOk()->assertJsonPath('data.unlocked', true);

    $data = getJson("/api/v1/me/jobs/{$item->id}/prep")->assertOk()->json('data');
    expect($data['unlocked'])->toBeTrue()
        ->and(count($data['questions']))->toBe($data['total'])
        ->and(app(EntitlementService::class)->balance($this->student, 'job_kit'))->toBe(0);

    // Unlocking again never double-spends.
    postJson("/api/v1/me/jobs/{$item->id}/unlock")->assertOk();
    expect(app(EntitlementService::class)->balance($this->student, 'job_kit'))->toBe(0);
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
        ->and($row['confidence_pct'])->toBe($row['match_pct'])
        ->and($row['unlocked'])->toBeFalse();
});

/* ---- Quick JD mock ---- */

it('starts a quick mock for an enrolled student via a hidden per-posting blueprint', function () {
    jobPrepEnrol($this->tenant, $this->student);
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

it('402s a JD mock for an outsider until they unlock the kit', function () {
    $item = prepItem($this->tenant);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$item->id}/mock")->assertStatus(402)
        ->assertJsonPath('error.code', 'job_kit_required');

    app(EntitlementService::class)->grantCredits($this->student, 'job_kit', 1, 'test');
    postJson("/api/v1/me/jobs/{$item->id}/unlock")->assertOk();
    postJson("/api/v1/me/jobs/{$item->id}/mock")->assertCreated();
});

it('404s a JD mock on another tenant\'s posting', function () {
    $other = Tenant::factory()->create();
    $foreign = prepItem($other);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$foreign->id}/mock")->assertNotFound();
});

/* ---- ₹299 bundle ---- */

it('settles the kit+mentor bundle: one job_kit credit plus one mentor credit', function () {
    Storage::fake('s3');
    app()->instance(RazorpayClient::class, new FakeRazorpayClient);
    $product = withinTenant($this->tenant, fn () => Product::factory()->for($this->tenant)->create([
        'sku' => 'job-kit-mentor', 'name' => 'Interview Kit + mentor 1:1 · one job', 'feature' => 'job_kit',
        'kind' => ProductKind::Pack->value, 'price_paise' => 29_900, 'grant_amount' => 1,
    ]));

    $purchase = withinTenant($this->tenant, fn () => app(StartPurchase::class)->handle($this->student, $product));
    withinTenant($this->tenant, fn () => app(SettlePurchase::class)->handle($purchase, 'pay_TEST1'));

    $svc = app(EntitlementService::class);
    expect($svc->balance($this->student, 'job_kit'))->toBe(1)
        ->and($svc->balance($this->student, 'mentor'))->toBe(1);
});
