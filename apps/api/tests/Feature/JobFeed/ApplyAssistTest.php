<?php

declare(strict_types=1);

use App\Models\JobApplication;
use App\Models\JobFeedItem;
use App\Models\JobFeedSave;
use App\Models\JobFeedSource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function activeItem(Tenant $tenant, array $overrides = []): JobFeedItem
{
    return withinTenant($tenant, function () use ($tenant, $overrides) {
        $source = JobFeedSource::factory()->for($tenant)->create();

        return JobFeedItem::factory()->for($tenant)->create(array_merge([
            'job_feed_source_id' => $source->id,
            'apply_url' => 'https://jobs.example.com/de-123',
        ], $overrides));
    });
}

it('applies: tailors a free CV, logs the application, returns the deep link', function () {
    $this->fake->reply = '{"summary":"S","skills":["SQL"],"experience":[],"projects":[],"education":[],"certifications":[],"headline":"H"}';
    $item = activeItem($this->tenant);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$item->id}/apply")->assertCreated()
        ->assertJsonPath('data.apply_url', 'https://jobs.example.com/de-123')
        ->assertJsonStructure(['data' => ['application_id', 'cv_document_id']]);

    $application = JobApplication::withoutGlobalScopes()->sole();
    expect($application->job_feed_item_id)->toBe($item->id)
        ->and($application->cv_document_id)->not->toBeNull()
        ->and($application->status)->toBe('applied');

    // Feed item is marked applied.
    expect(JobFeedSave::withoutGlobalScopes()->where('job_feed_item_id', $item->id)->value('state'))->toBe('applied');
});

it('does not let a student apply to the same item twice', function () {
    $this->fake->reply = '{"summary":"S","skills":[],"experience":[],"projects":[],"education":[],"certifications":[],"headline":"H"}';
    $item = activeItem($this->tenant);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$item->id}/apply")->assertCreated();
    postJson("/api/v1/me/jobs/{$item->id}/apply")->assertStatus(422);

    expect(JobApplication::withoutGlobalScopes()->count())->toBe(1);
});

it('404s applying to another tenant item', function () {
    $other = Tenant::factory()->create();
    $item = activeItem($other);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$item->id}/apply")->assertNotFound();
});

it('404s applying to an expired item', function () {
    $item = activeItem($this->tenant, ['status' => JobFeedItem::STATUS_EXPIRED]);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/jobs/{$item->id}/apply")->assertNotFound();
});

it('gates Apply Copilot behind its feature flag', function () {
    $item = activeItem($this->tenant);
    Sanctum::actingAs($this->student);

    // Off by default.
    postJson("/api/v1/me/jobs/{$item->id}/copilot")->assertForbidden();

    // On → reachable, but a stub (never auto-submits).
    config(['features.apply_copilot' => true]);
    postJson("/api/v1/me/jobs/{$item->id}/copilot")->assertStatus(501);
});

it('requires authentication to apply', function () {
    $item = activeItem($this->tenant);
    postJson("/api/v1/me/jobs/{$item->id}/apply")->assertUnauthorized();
});
