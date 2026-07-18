<?php

declare(strict_types=1);

use App\Actions\JobFeed\IngestJobFeed;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\JobFeed\JobApiTransport;
use App\Support\JobFeed\NormalizedJob;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
});

function feedOfficer(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('placement-officer');

    return $user;
}

/** A fake licensed-API transport returning canned postings (no external call). */
function bindFakeTransport(array $jobs): void
{
    app()->instance(JobApiTransport::class, new class($jobs) implements JobApiTransport
    {
        public function __construct(private array $jobs) {}

        public function search(array $query): array
        {
            return $this->jobs;
        }
    });
}

it('ingests internal postings into the feed and extracts skills', function () {
    $this->fake->reply = '{"role_title":"Data Engineer","seniority":"mid","location":"Bengaluru","skills":["python","sql"],"quality_score":80}';
    $source = withinTenant($this->tenant, function () {
        JobPosting::factory()->for($this->tenant)->create(['title' => 'Data Engineer', 'company' => 'Acme', 'description' => 'Build pipelines with Python and SQL across the platform.']);

        return JobFeedSource::factory()->for($this->tenant)->create(['kind' => 'internal']);
    });

    $summary = withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));
    expect($summary['ingested'])->toBe(1);

    $item = JobFeedItem::withoutGlobalScopes()->where('company', 'Acme')->sole();
    expect($item->extracted_skills)->toContain('python', 'sql')
        ->and($item->expires_at)->not->toBeNull();
});

it('dedupes the same posting across syncs', function () {
    $this->fake->reply = '{"skills":["python"]}';
    $source = withinTenant($this->tenant, function () {
        JobPosting::factory()->for($this->tenant)->create(['title' => 'DE', 'company' => 'Acme', 'description' => str_repeat('Python pipelines. ', 5)]);

        return JobFeedSource::factory()->for($this->tenant)->create(['kind' => 'internal']);
    });

    withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));
    $second = withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($second['ingested'])->toBe(0)->and($second['duplicates'])->toBe(1);
    expect(JobFeedItem::withoutGlobalScopes()->count())->toBe(1);
});

it('pulls from a licensed-API source via the swappable transport', function () {
    $this->fake->reply = '{"skills":["kafka"]}';
    bindFakeTransport([
        new NormalizedJob(title: 'Streaming Engineer', company: 'Globex', description: 'Own Kafka pipelines and streaming infrastructure for the data platform.', externalId: 'jsearch-1'),
    ]);
    $source = withinTenant($this->tenant, fn () => JobFeedSource::factory()->for($this->tenant)->create(['kind' => 'api']));

    $summary = withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($summary['ingested'])->toBe(1);
    expect(JobFeedItem::withoutGlobalScopes()->where('external_id', 'jsearch-1')->exists())->toBeTrue();
});

it('skips thin postings as low quality', function () {
    $source = withinTenant($this->tenant, fn () => JobFeedSource::factory()->for($this->tenant)->create(['kind' => 'api']));
    bindFakeTransport([new NormalizedJob(title: 'X', company: 'Y', description: 'too short')]);

    $summary = withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));
    expect($summary['skipped'])->toBe(1)->and($summary['ingested'])->toBe(0);
});

it('imports a CSV batch through the admin endpoint', function () {
    $this->fake->reply = '{"skills":["sql"]}';
    $source = withinTenant($this->tenant, fn () => JobFeedSource::factory()->for($this->tenant)->create(['kind' => 'csv']));
    Sanctum::actingAs(feedOfficer($this->tenant));

    $csv = "title,company,description,location\n"
        ."Data Analyst,Globex,\"SQL and Power BI dashboards for the reporting team here.\",Pune\n";
    $file = UploadedFile::fake()->createWithContent('jobs.csv', $csv);

    postJson("/api/v1/admin/job-feed/sources/{$source->id}/import", ['file' => $file])->assertOk()
        ->assertJsonPath('data.ingested', 1);
});

it('gates the feed admin behind the placement ability', function () {
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));
    getJson('/api/v1/admin/job-feed')->assertForbidden();
});

it('does not leak feed items across tenants', function () {
    $other = Tenant::factory()->create();
    $otherSource = JobFeedSource::factory()->for($other)->create();
    JobFeedItem::factory()->for($other)->create(['job_feed_source_id' => $otherSource->id, 'company' => 'SecretCo']);

    Sanctum::actingAs(feedOfficer($this->tenant));
    getJson('/api/v1/admin/job-feed')->assertOk()->assertJsonPath('data.recent', []);
});
