<?php

declare(strict_types=1);

use App\Models\CvProfile;
use App\Models\JobFeedItem;
use App\Models\JobFeedSave;
use App\Models\JobFeedSource;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    // The student knows Python and SQL.
    withinTenant($this->tenant, fn () => CvProfile::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->student->id,
        'data' => ['skills' => ['Python', 'SQL']],
    ]));
});

function feedItem(Tenant $tenant, array $skills, array $overrides = []): JobFeedItem
{
    return withinTenant($tenant, function () use ($tenant, $skills, $overrides) {
        $source = JobFeedSource::factory()->for($tenant)->create();

        return JobFeedItem::factory()->for($tenant)->create(array_merge([
            'job_feed_source_id' => $source->id,
            'extracted_skills' => $skills,
        ], $overrides));
    });
}

it('scores relevance and explains the match and the gap', function () {
    feedItem($this->tenant, ['python', 'sql', 'kafka'], ['title' => 'Data Engineer', 'company' => 'Acme']);
    Sanctum::actingAs($this->student);

    $row = getJson('/api/v1/me/jobs')->assertOk()->json('data.0');

    expect($row['matched'])->toContain('python', 'sql')
        ->and($row['gap'])->toContain('kafka')
        ->and($row['match_pct'])->toBeGreaterThan(40);
});

it('pins saved items and drops dismissed ones', function () {
    $keep = feedItem($this->tenant, ['python'], ['company' => 'KeepCo']);
    $drop = feedItem($this->tenant, ['python'], ['company' => 'DropCo']);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$keep->id}/save")->assertOk();
    postJson("/api/v1/me/jobs/{$drop->id}/dismiss")->assertOk();

    $companies = collect(getJson('/api/v1/me/jobs')->assertOk()->json('data'))->pluck('company');
    expect($companies)->toContain('KeepCo')->not->toContain('DropCo');
    expect(getJson('/api/v1/me/jobs')->json('data.0.saved'))->toBeTrue();
});

it('excludes expired items', function () {
    feedItem($this->tenant, ['python'], ['company' => 'Fresh', 'expires_at' => now()->addDay()]);
    feedItem($this->tenant, ['python'], ['company' => 'Stale', 'status' => JobFeedItem::STATUS_EXPIRED, 'expires_at' => now()->subDay()]);
    Sanctum::actingAs($this->student);

    $companies = collect(getJson('/api/v1/me/jobs')->json('data'))->pluck('company');
    expect($companies)->toContain('Fresh')->not->toContain('Stale');
});

it('does not surface another tenant feed', function () {
    $other = Tenant::factory()->create();
    feedItem($other, ['python'], ['company' => 'OtherCo']);
    Sanctum::actingAs($this->student);

    expect(getJson('/api/v1/me/jobs')->json('data'))->toBe([]);
});

it('404s save on another tenant item', function () {
    $other = Tenant::factory()->create();
    $item = feedItem($other, ['python']);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$item->id}/save")->assertNotFound();
    expect(JobFeedSave::withoutGlobalScopes()->count())->toBe(0);
});

it('requires authentication', function () {
    getJson('/api/v1/me/jobs')->assertUnauthorized();
});
