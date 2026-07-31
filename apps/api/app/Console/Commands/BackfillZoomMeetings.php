<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LiveSessionStatus;
use App\Jobs\CreateZoomMeeting;
use App\Models\LiveSession;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Self-heal for classes whose Zoom meeting was never created (API outage,
 * invalid credentials at schedule time): re-queues CreateZoomMeeting for every
 * future scheduled session still missing its meeting. Idempotent — sessions
 * with a meeting are untouched. Runs hourly, so fixing the Zoom credentials
 * in .env repairs every pending class within the hour with no manual step.
 */
final class BackfillZoomMeetings extends Command
{
    protected $signature = 'zoom:backfill';

    protected $description = 'Queue Zoom meeting creation for scheduled classes that are missing one';

    public function handle(): int
    {
        $sessions = LiveSession::query()->withoutGlobalScope(TenantScope::class)
            ->where('status', LiveSessionStatus::Scheduled->value)
            ->whereNull('zoom_meeting_id')
            ->where('scheduled_start', '>', now())
            ->get();

        foreach ($sessions as $session) {
            CreateZoomMeeting::dispatch($session->id);
        }

        $this->info("Queued Zoom creation for {$sessions->count()} class(es).");

        return self::SUCCESS;
    }
}
