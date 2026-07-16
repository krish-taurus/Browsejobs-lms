<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fees\RunDunningLadder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs the dunning ladder (PRD §6.8): reminders + soft/hard access blocks for
 * overdue fees. Scheduled daily; idempotent, so a manual re-run is safe. The
 * `--date` option evaluates the ladder as of a given day (for demos/backfill).
 */
final class RunFeeLadder extends Command
{
    protected $signature = 'fees:run-ladder {--date= : Evaluate as of this date (Y-m-d); defaults to today}';

    protected $description = 'Send dunning reminders and apply/promote fee access blocks for overdue students';

    public function handle(RunDunningLadder $ladder): int
    {
        $date = $this->option('date');
        $today = is_string($date) && $date !== '' ? Carbon::parse($date) : Carbon::today();

        $summary = $ladder->handle($today);

        $this->info(sprintf(
            'Dunning ladder (%s): %d reminders, %d soft blocks, %d hard blocks.',
            $today->toDateString(),
            $summary['reminders'],
            $summary['soft_blocks'],
            $summary['hard_blocks'],
        ));

        return self::SUCCESS;
    }
}
