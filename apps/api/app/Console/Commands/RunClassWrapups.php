<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\RunClassWrapups as RunClassWrapupsAction;
use Illuminate\Console\Command;

/**
 * Post-class study nudge (PRD §6 daily loop). Nudges each ended class's cohort to
 * review its flashcards, once the flashcards exist. Scheduled hourly; idempotent
 * (`wrapped_up_at` dedups a wrapped class).
 */
final class RunClassWrapups extends Command
{
    protected $signature = 'class:wrapup';

    protected $description = 'Nudge cohorts to review flashcards for classes that have ended';

    public function handle(RunClassWrapupsAction $action): int
    {
        $wrapped = $action->handle();
        $this->info("Classes wrapped: {$wrapped}.");

        return self::SUCCESS;
    }
}
