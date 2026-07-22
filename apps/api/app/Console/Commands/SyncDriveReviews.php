<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reviews\SyncDriveReviews as SyncDriveReviewsAction;
use Illuminate\Console\Command;

/**
 * Pulls new review images from the Google Drive folder into the intake queue
 * (Platform Spec §3). `--check` only reports how many images the service account
 * can see — a quick connection test before the first real sync. Scheduled weekly.
 */
final class SyncDriveReviews extends Command
{
    protected $signature = 'reviews:sync-drive {--check : Report how many images are visible, without importing}';

    protected $description = 'Sync new review images from the configured Google Drive folder into the intake queue';

    public function handle(SyncDriveReviewsAction $action): int
    {
        if ($this->option('check')) {
            $this->info("Drive images visible to the service account: {$action->count()}.");

            return self::SUCCESS;
        }

        $created = $action->handle();
        $this->info("New reviews synced into the intake queue: {$created}.");

        return self::SUCCESS;
    }
}
