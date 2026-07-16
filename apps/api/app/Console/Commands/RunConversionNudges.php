<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Conversion\RunConversionNudges as RunConversionNudgesAction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs the bootcamp-conversion non-payer nudge ladder (PRD §5 Stage 3): payment
 * reminders + counselor tasks for converted-but-unpaid students. Scheduled daily;
 * idempotent. `--date` evaluates as of a given day (demos/backfill).
 */
final class RunConversionNudges extends Command
{
    protected $signature = 'conversions:run-nudges {--date= : Evaluate as of this date (Y-m-d); defaults to today}';

    protected $description = 'Send bootcamp-conversion payment nudges and raise counselor tasks for non-payers';

    public function handle(RunConversionNudgesAction $ladder): int
    {
        $date = $this->option('date');
        $today = is_string($date) && $date !== '' ? Carbon::parse($date) : Carbon::today();

        $summary = $ladder->handle($today);

        $this->info(sprintf(
            'Conversion nudges (%s): %d nudges, %d counselor tasks.',
            $today->toDateString(),
            $summary['nudges'],
            $summary['tasks'],
        ));

        return self::SUCCESS;
    }
}
