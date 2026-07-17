<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reports\RunWeeklyReports as RunWeeklyReportsAction;
use Illuminate\Console\Command;

/**
 * Weekly AI student reports (PRD §6.10). Queues one report per active student.
 */
final class RunWeeklyReports extends Command
{
    protected $signature = 'reports:weekly';

    protected $description = 'Queue the weekly AI progress report for every active student';

    public function handle(RunWeeklyReportsAction $action): int
    {
        $summary = $action->handle();

        $this->info("Queued {$summary['queued']} weekly report(s).");

        return self::SUCCESS;
    }
}
