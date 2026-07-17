<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Quizzes\RunQuizDispatchSweep;
use Illuminate\Console\Command;

/**
 * Hourly safety-net sweep for the quiz-dispatch reminder ladder (PRD §6.5) — backstop
 * behind the delayed CheckQuizCompletion jobs.
 */
final class CheckQuizDispatch extends Command
{
    protected $signature = 'quizzes:check-dispatch';

    protected $description = 'Re-queue quiz reminder/counselor-flag jobs whose window has passed';

    public function handle(RunQuizDispatchSweep $sweep): int
    {
        $summary = $sweep->handle();

        $this->info("Queued {$summary['reminders']} reminder(s), {$summary['flags']} flag(s).");

        return self::SUCCESS;
    }
}
