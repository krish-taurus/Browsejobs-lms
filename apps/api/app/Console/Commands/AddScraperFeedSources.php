<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JobFeedSource;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Provision one scraper source per track against a given Apify actor.
 *
 * The market boards count postings we ingest, so their honesty depends on the
 * feed being wide enough to count. Configuring that by hand means filling the
 * same admin form four times with the search terms tuned differently each
 * time, which is exactly the kind of chore that never gets finished.
 *
 * The actor id is a required argument rather than a default, deliberately.
 * Apify marketplace actors change owner and name, and a wrong id produces a
 * source that runs, returns nothing, and looks configured — so the id has to
 * come from the operator's own Apify console, not from a guess baked in here.
 *
 * Usage:
 *   php artisan feed:add-scraper "LinkedIn" bebity/linkedin-jobs-scraper
 *   php artisan feed:add-scraper "Naukri" some/naukri-actor --location="India"
 *
 * Then check it actually pulls:
 *   php artisan feed:test
 */
final class AddScraperFeedSources extends Command
{
    protected $signature = 'feed:add-scraper
        {board : Label for the board, e.g. LinkedIn}
        {actor : Apify actor id, e.g. user/actor-name — copy it from your Apify console}
        {--location=India : Location passed to the actor}
        {--rows=100 : Results per run, per track}
        {--tenant= : Tenant slug; defaults to the first tenant}';

    protected $description = 'Create one scraper feed source per track for a job board.';

    /**
     * The four tracks the boards report on, with a query tuned per track and
     * the terms that most often pollute it.
     */
    private const TRACKS = [
        'Data Engineering' => [
            'query' => '"data engineer"',
            'exclude' => ['sales', 'manager', 'intern', 'salesforce'],
        ],
        'DevOps & Cloud' => [
            'query' => '"devops engineer" OR "site reliability engineer"',
            'exclude' => ['sales', 'intern', 'support'],
        ],
        'Python Backend' => [
            'query' => '"python developer" OR "backend engineer"',
            'exclude' => ['intern', 'trainee', 'sales'],
        ],
        'Data Analytics' => [
            'query' => '"data analyst" OR "business analyst"',
            'exclude' => ['intern', 'sales', 'recruiter'],
        ],
    ];

    public function handle(): int
    {
        $slug = (string) $this->option('tenant');
        $tenant = $slug !== ''
            ? Tenant::query()->where('slug', $slug)->first()
            : Tenant::query()->orderBy('id')->first();

        if ($tenant === null) {
            $this->error($slug !== '' ? "No tenant [{$slug}]." : 'No tenants exist yet.');

            return self::FAILURE;
        }

        $board = trim((string) $this->argument('board'));
        $actor = trim((string) $this->argument('actor'));
        $created = 0;

        foreach (self::TRACKS as $track => $spec) {
            $name = "{$board} — {$track}";

            $source = JobFeedSource::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('name', $name)
                ->first();

            $config = [
                'actor_id' => $actor,
                'exclude' => $spec['exclude'],
                'input' => [
                    // Actors name these differently; ScraperAdapter tightens
                    // whichever fields are present, so sending both is safe.
                    'query' => $spec['query'],
                    'keyword' => $spec['query'],
                    'location' => (string) $this->option('location'),
                    'rows' => (int) $this->option('rows'),
                ],
            ];

            if ($source !== null) {
                // Re-running with a corrected actor id should fix the source,
                // not create a second one beside it.
                $source->update(['config' => $config, 'is_active' => true]);
                $this->line("updated  {$name}");

                continue;
            }

            JobFeedSource::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'kind' => JobFeedSource::KIND_SCRAPER,
                'config' => $config,
                'priority' => 20,
                'is_active' => true,
            ]);

            $created++;
            $this->line("created  {$name}");
        }

        $this->newLine();
        $this->info("{$board}: ".count(self::TRACKS)." sources ready ({$created} new).");
        $this->line('Nothing has been pulled yet. Check the actor actually returns postings:');
        $this->line('  php artisan feed:test');
        $this->line('Then sync for real:');
        $this->line('  php artisan feed:sync');

        return self::SUCCESS;
    }
}
