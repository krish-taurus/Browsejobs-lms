<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\JobFeed\IngestJobFeed;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Syncs the live job feed per active tenant (PRD §6.22): pulls each active
 * source through its adapter and expires stale items past their freshness
 * window. Idempotent — safe to run often.
 */
final class SyncJobFeeds extends Command
{
    protected $signature = 'feed:sync';

    protected $description = 'Pull active job-feed sources and expire stale items';

    public function handle(IngestJobFeed $ingest): int
    {
        $ingested = 0;
        $expired = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            app(TenantContext::class)->run($tenant, function () use ($ingest, &$ingested, &$expired): void {
                foreach (JobFeedSource::query()->where('is_active', true)->get() as $source) {
                    $summary = $ingest->fromSource($source);
                    $ingested += $summary['ingested'];
                }

                $expired += JobFeedItem::query()
                    ->where('status', JobFeedItem::STATUS_ACTIVE)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->update(['status' => JobFeedItem::STATUS_EXPIRED]);
            });
        }

        $this->info("Ingested {$ingested} item(s); expired {$expired}.");

        return self::SUCCESS;
    }
}
