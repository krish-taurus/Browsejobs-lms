<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fees\ReconcileFeeBlocks;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Midnight safety net behind the instant unblock: anyone blocked for fees who
 * no longer owes anything overdue gets their access back. Idempotent.
 */
final class ReconcileFeeBlocksCommand extends Command
{
    protected $signature = 'fees:reconcile-blocks {--date= : Evaluate as of this date (Y-m-d); defaults to today}';

    protected $description = 'Lift fee access blocks for students who no longer owe anything overdue';

    public function handle(ReconcileFeeBlocks $reconcile): int
    {
        $date = $this->option('date');
        $today = is_string($date) && $date !== '' ? Carbon::parse($date) : Carbon::today();

        $summary = $reconcile->handle($today);

        $this->info(sprintf(
            'Fee block check (%s): %d blocked student(s) checked, %d unblocked, %d still owe.',
            $today->toDateString(),
            $summary['checked'],
            $summary['lifted'],
            $summary['kept'],
        ));

        return self::SUCCESS;
    }
}
