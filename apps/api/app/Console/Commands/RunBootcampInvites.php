<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Conversion\RunBootcampInvites as RunBootcampInvitesAction;
use Illuminate\Console\Command;

/**
 * Auto-invites masterclass leads to the free 7-day bootcamp (funnel Stage 2→3).
 * Scheduled daily; idempotent (advancing the lead's stage dedups it).
 */
final class RunBootcampInvites extends Command
{
    protected $signature = 'bootcamp:invite';

    protected $description = 'Invite masterclass leads to the free 7-day bootcamp and advance their stage';

    public function handle(RunBootcampInvitesAction $action): int
    {
        $invited = $action->handle();
        $this->info("Bootcamp invites sent: {$invited}.");

        return self::SUCCESS;
    }
}
