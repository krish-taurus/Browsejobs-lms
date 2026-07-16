<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Support\RunTicketSlaSweep;
use Illuminate\Console\Command;

/**
 * Support-ticket SLA sweep (PRD §6.13). Re-checks active tickets whose SLA passed
 * unflagged — the safety net behind the delayed per-ticket jobs. Scheduled hourly;
 * idempotent.
 */
final class CheckTicketSla extends Command
{
    protected $signature = 'support:check-sla';

    protected $description = 'Re-check support tickets whose SLA due-time passed unflagged';

    public function handle(RunTicketSlaSweep $sweep): int
    {
        $summary = $sweep->handle();

        $this->info("Support SLA sweep: {$summary['swept']} ticket(s) re-checked.");

        return self::SUCCESS;
    }
}
