<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Scoring\RecomputeAllScores;
use Illuminate\Console\Command;

/**
 * Nightly rule-based rescore of active students (PRD §6.4): mastery/engagement/
 * risk/PRI + daily snapshots for trajectory + counselor movers. Idempotent.
 */
final class RecomputeScores extends Command
{
    protected $signature = 'scores:recompute';

    protected $description = 'Recompute rule-based student scores + daily snapshots';

    public function handle(RecomputeAllScores $action): int
    {
        $summary = $action->handle();

        $this->info("Scored {$summary['scored']} student(s).");

        return self::SUCCESS;
    }
}
