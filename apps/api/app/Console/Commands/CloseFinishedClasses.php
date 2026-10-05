<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use Illuminate\Console\Command;

/**
 * Marks classes finished once their slot has passed.
 *
 * Nothing else did this: a class stayed `scheduled` for ever unless a human
 * touched it, because Zoom never tells us a meeting is over. Everything that
 * asks "is this class done?" was therefore answered wrongly — finished classes
 * sat in the student's Upcoming list, the recording sync skipped them ("0 from 3
 * candidates"), and the portal showed a dead class as "Not ready yet".
 *
 * The grace window matters: a class often over-runs its scheduled end, so we
 * only close it once the slot has been over for a while.
 */
final class CloseFinishedClasses extends Command
{
    /** Minutes past the scheduled end before a class is considered over. */
    private const GRACE_MINUTES = 60;

    protected $signature = 'classes:close-finished {--dry-run : List what would close without changing anything}';

    protected $description = 'Mark scheduled/live classes as ended once their slot has passed';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = LiveSession::query()->withoutGlobalScopes()
            ->whereIn('status', [LiveSessionStatus::Scheduled->value, LiveSessionStatus::Live->value])
            ->where('scheduled_start', '<', now())
            ->orderBy('scheduled_start')
            ->get()
            ->filter(fn (LiveSession $s) => $this->finishedAt($s)->isPast());

        foreach ($candidates as $session) {
            $this->line(sprintf(
                '%s %s — %s',
                $dryRun ? 'would close' : 'closed',
                $session->scheduled_start->format('d M Y, g:i A'),
                $session->title ?: 'Untitled class',
            ));

            if (! $dryRun) {
                $session->forceFill(['status' => LiveSessionStatus::Ended->value])->save();
            }
        }

        $this->info(($dryRun ? 'Would close ' : 'Closed ').$candidates->count().' class(es).');

        return self::SUCCESS;
    }

    /** When the slot is considered over, allowing for over-running. */
    private function finishedAt(LiveSession $session): \Illuminate\Support\Carbon
    {
        $end = $session->scheduled_end
            ?? $session->scheduled_start->copy()->addSeconds($session->plannedSeconds());

        return $end->copy()->addMinutes(self::GRACE_MINUTES);
    }
}
