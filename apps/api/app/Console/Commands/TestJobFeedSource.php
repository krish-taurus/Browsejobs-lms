<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JobFeedSource;
use App\Support\JobFeed\FeedRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pull one source and report what came back, without ingesting anything.
 *
 * A misconfigured scraper fails quietly today: the Apify transport logs a
 * warning and returns an empty array, so a dead actor id, an exhausted Apify
 * balance and a genuinely quiet search all look identical from the admin
 * screen — the source just never brings anything in. That silence is how a
 * feed stays thin without anyone noticing, and the market boards downstream
 * then honestly report that they have too few postings to say anything.
 *
 * Usage: php artisan feed:test            (every active source)
 *        php artisan feed:test 3          (one source)
 */
final class TestJobFeedSource extends Command
{
    protected $signature = 'feed:test {source? : Source id; omit to test every active source}';

    protected $description = 'Pull each job-feed source once and report what it returned. Ingests nothing.';

    public function handle(FeedRegistry $registry): int
    {
        $sources = JobFeedSource::withoutGlobalScopes()
            ->when($this->argument('source'), fn ($q) => $q->whereKey($this->argument('source')))
            ->when(! $this->argument('source'), fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            $this->error('No matching sources. Add one in Admin → Job feed, or run feed:add-scraper.');

            return self::FAILURE;
        }

        $rows = [];
        $anyReturned = false;

        foreach ($sources as $source) {
            [$count, $note] = $this->pull($registry, $source);
            $anyReturned = $anyReturned || $count > 0;

            $rows[] = [
                $source->id,
                $source->name,
                $source->kind,
                $source->is_active ? 'yes' : 'no',
                $count === null ? '—' : (string) $count,
                $note,
            ];
        }

        $this->table(['ID', 'Source', 'Kind', 'Active', 'Returned', 'Note'], $rows);

        if (! $anyReturned) {
            $this->newLine();
            $this->error('No source returned a single posting.');
            $this->line('For a scraper that usually means the actor id is wrong, the Apify');
            $this->line('token is missing (Admin → Settings → Job scrapers), or the account');
            $this->line('is out of credit. The run itself is logged — check laravel.log.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function pull(FeedRegistry $registry, JobFeedSource $source): array
    {
        $adapter = $registry->for($source->kind);

        if ($adapter === null) {
            return [null, 'pushed, not pulled — nothing to test'];
        }

        if ($source->kind === JobFeedSource::KIND_SCRAPER
            && trim((string) ($source->config['actor_id'] ?? '')) === '') {
            return [0, 'no actor_id configured'];
        }

        try {
            $tenant = $source->tenant;

            $jobs = $tenant === null
                ? $adapter->pull($source)
                : app(TenantContext::class)->run($tenant, fn (): array => $adapter->pull($source));

            if ($jobs === []) {
                return [0, 'ran, returned nothing'];
            }

            // The first title is the fastest way to see whether the query is
            // pulling the roles you meant — a source returning 200 sales jobs
            // is "working" and still useless.
            $first = $jobs[0]->title ?? '';

            return [count($jobs), mb_substr('e.g. '.$first, 0, 60)];
        } catch (Throwable $e) {
            return [0, class_basename($e).': '.mb_substr($e->getMessage(), 0, 50)];
        }
    }
}
