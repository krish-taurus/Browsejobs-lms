<?php

declare(strict_types=1);

use App\Actions\JobFeed\IngestJobFeed;
use App\Jobs\ExtractJobFeedItemSkills;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\JobFeed\ApifyTransport;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * Scraper job-feed sources via Apify actors (ADR 0048). The transport is faked
 * — no external call ever happens here.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

/** A fake Apify transport returning canned dataset items. */
function fakeApify(array $items): ApifyTransport
{
    $fake = new class implements ApifyTransport
    {
        /** @var list<array<string, mixed>> */
        public array $items = [];

        /** @var list<array{actor: string, input: array<string, mixed>}> */
        public array $runs = [];

        public function run(string $actorId, array $input): array
        {
            $this->runs[] = ['actor' => $actorId, 'input' => $input];

            return $this->items;
        }
    };
    $fake->items = $items;
    app()->instance(ApifyTransport::class, $fake);

    return $fake;
}

function scraperSource(Tenant $tenant, array $config = []): JobFeedSource
{
    return withinTenant($tenant, fn () => JobFeedSource::factory()->for($tenant)->create([
        'kind' => JobFeedSource::KIND_SCRAPER,
        'config' => array_merge(['actor_id' => 'acme/naukri-scraper', 'input' => ['query' => 'data engineer'], 'freshness_days' => 7], $config),
    ]));
}

it('ingests scraped postings with tolerant field mapping and a 7-day expiry', function () {
    Queue::fake(); // skill extraction is queued per item; not under test here
    fakeApify([
        // Naukri-ish shape.
        ['title' => 'Data Engineer', 'companyName' => 'Acme Analytics', 'jobDescription' => str_repeat('Build pipelines with Spark and SQL. ', 5), 'placeholders' => true, 'location' => 'Bengaluru', 'jobUrl' => 'https://naukri.test/j/1', 'postedDate' => now()->subDay()->toIso8601String()],
        // LinkedIn-ish shape.
        ['jobTitle' => 'Backend Developer', 'company' => 'Beta Soft', 'descriptionText' => str_repeat('Python microservices on AWS. ', 5), 'link' => 'https://linkedin.test/j/2', 'workplaceType' => 'Remote', 'listedAt' => now()->subDays(2)->toIso8601String()],
        // Not a job row — skipped by mapping.
        ['somethingElse' => 'noise'],
    ]);

    $source = scraperSource($this->tenant);
    $summary = withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($summary['ingested'])->toBe(2);

    $items = JobFeedItem::withoutGlobalScopes()->orderBy('id')->get();
    expect($items)->toHaveCount(2)
        ->and($items[0]->company)->toBe('Acme Analytics')
        ->and($items[0]->apply_url)->toBe('https://naukri.test/j/1')
        ->and($items[1]->work_mode)->toBe('remote')
        // The founder's rolling window: scraped postings expire 7 days after posting.
        ->and($items[0]->expires_at->diffInDays($items[0]->posted_at))->toBeLessThanOrEqual(7.0);
});

it('maps the LinkedIn jobs actor output shape, storing its skills and skipping AI extraction', function () {
    Queue::fake();
    // Exact field shape of the founder's Apify run (actor PeTP8M7vkdTthJvqk).
    fakeApify([[
        'jobUrl' => 'https://www.linkedin.com/jobs/view/4440685444',
        'jobTitle' => 'Data Engineer',
        'companyName' => 'JB Group of Companies',
        'location' => 'Mumbai Metropolitan Region, IN',
        'employmentType' => 'Full-time',
        'seniorityLevel' => 'Associate',
        'postedDate' => '2026-07-16',
        'jobDescription' => str_repeat('Advanced analytics with Python, Pandas, MySQL and BigQuery pipelines. ', 5),
        'skills' => ['Data Analysis', 'GCP', 'Machine Learning', 'MySQL', 'Python', 'SQL'],
        'applyUrl' => 'https://www.linkedin.com/jobs/view/4440685444',
    ]]);

    $source = scraperSource($this->tenant);
    withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    $item = JobFeedItem::withoutGlobalScopes()->sole();
    expect($item->extracted_skills)->toBe(['data analysis', 'gcp', 'machine learning', 'mysql', 'python', 'sql'])
        ->and($item->seniority)->toBe('Associate')
        ->and($item->posted_at->toDateString())->toBe('2026-07-16')
        ->and($item->apply_url)->toBe('https://www.linkedin.com/jobs/view/4440685444');

    // Skills came from the actor — no AI extraction job was queued for it.
    Queue::assertNotPushed(ExtractJobFeedItemSkills::class);
});

it('tightens a plain query to an exact phrase with NOT exclusions before calling the actor', function () {
    Queue::fake();
    $fake = fakeApify([]);
    $source = scraperSource($this->tenant, [
        'input' => ['query' => 'Data engineer', 'location' => 'India'],
        'exclude' => ['Salesforce', 'Frontend', 'Digital Marketing'],
    ]);

    withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($fake->runs)->toHaveCount(1)
        ->and($fake->runs[0]['input']['query'])->toBe('"Data engineer" NOT Salesforce NOT Frontend NOT "Digital Marketing"')
        ->and($fake->runs[0]['input']['location'])->toBe('India'); // rest of the input untouched
});

it('tightens the Naukri actor\'s keyword field the same way', function () {
    Queue::fake();
    $fake = fakeApify([]);
    $source = scraperSource($this->tenant, [
        'input' => ['keyword' => 'python developer', 'maxResults' => 100, 'fetchDetails' => true],
        'exclude' => ['Sales'],
    ]);

    withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($fake->runs[0]['input']['keyword'])->toBe('"python developer" NOT Sales')
        ->and($fake->runs[0]['input']['fetchDetails'])->toBeTrue();
});

it('leaves an admin-written boolean query untouched', function () {
    Queue::fake();
    $fake = fakeApify([]);
    $source = scraperSource($this->tenant, [
        'input' => ['query' => '"Data Engineer" OR "ETL Developer"'],
        'exclude' => [],
    ]);

    withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($fake->runs[0]['input']['query'])->toBe('"Data Engineer" OR "ETL Developer"');
});

it('re-ingesting the same postings creates no duplicates', function () {
    Queue::fake();
    fakeApify([
        ['title' => 'Data Engineer', 'companyName' => 'Acme', 'jobDescription' => str_repeat('Spark pipelines. ', 5), 'jobUrl' => 'https://naukri.test/j/1', 'id' => 'N-1'],
    ]);
    $source = scraperSource($this->tenant);

    withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));
    $second = withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($second['duplicates'])->toBe(1)
        ->and(JobFeedItem::withoutGlobalScopes()->count())->toBe(1);
});

it('syncs to nothing when no Apify token is configured (null transport)', function () {
    Queue::fake();
    config(['services.apify.token' => '']);
    $source = scraperSource($this->tenant);

    $summary = withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect($summary['ingested'])->toBe(0)
        ->and(JobFeedItem::withoutGlobalScopes()->count())->toBe(0);
});

it('ingests scraped items only into the source\'s own tenant', function () {
    Queue::fake();
    fakeApify([
        ['title' => 'Data Engineer', 'companyName' => 'Acme', 'jobDescription' => str_repeat('Spark pipelines. ', 5)],
    ]);
    $other = Tenant::factory()->create();
    $source = scraperSource($this->tenant);

    withinTenant($this->tenant, fn () => app(IngestJobFeed::class)->fromSource($source));

    expect(JobFeedItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and(JobFeedItem::withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(0);
});

it('lets an admin create a scraper source with an actor id, defaulting freshness to 7 days', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson('/api/v1/admin/job-feed/sources', [
        'name' => 'Naukri · data roles',
        'kind' => 'scraper',
        'config' => ['actor_id' => 'acme/naukri-scraper', 'input' => ['query' => 'data engineer']],
    ])->assertCreated();

    $source = JobFeedSource::withoutGlobalScopes()->sole();
    expect($source->config['actor_id'])->toBe('acme/naukri-scraper')
        ->and($source->config['freshness_days'])->toBe(7);

    // Scraper without an actor id is rejected.
    postJson('/api/v1/admin/job-feed/sources', ['name' => 'Broken', 'kind' => 'scraper'])
        ->assertUnprocessable();
});
