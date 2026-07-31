<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\LiveSessionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Moves any batch's class to a new slot — Zoom meeting updated, students and
 * staff notified with old vs new time, reminder ladder re-armed. The
 * masterclass:reschedule command moves a whole masterclass batch; this one
 * moves a single session of a bootcamp/paid batch. Driven by the CRM.
 */
final class RescheduleClass extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'class:reschedule
        {session : Live session id}
        {date : New date (Y-m-d)}
        {time : New start time (HH:MM, 24h)}
        {--duration= : New length in minutes (defaults to the previous length)}
        {--reason=Schedule change : Shown to students in the notification}';

    protected $description = 'Reschedule a single class (Zoom + notifications + reminder ladder)';

    public function handle(RescheduleLiveSession $reschedule): int
    {
        $session = $this->findSession((string) $this->argument('session'));

        if ($session === null) {
            $this->error("Session '{$this->argument('session')}' not found.");

            return self::FAILURE;
        }

        if ($session->status === LiveSessionStatus::Cancelled) {
            $this->error('This class is cancelled — schedule a new one instead.');

            return self::FAILURE;
        }

        $newStart = Carbon::parse($this->argument('date').' '.$this->argument('time'));

        if ($newStart->isPast()) {
            $this->error('New class time is in the past — pick a future slot.');

            return self::FAILURE;
        }

        $minutes = $this->option('duration') !== null
            ? (int) $this->option('duration')
            : ($session->scheduled_end !== null ? (int) $session->scheduled_start->diffInMinutes($session->scheduled_end) : 90);

        return $this->runForTenant($session->tenant_id, function () use ($session, $newStart, $minutes, $reschedule): int {
            $reschedule->handle(
                $session,
                $newStart,
                $newStart->copy()->addMinutes($minutes),
                (string) $this->option('reason'),
            );

            $this->info("Class #{$session->id} moved to {$newStart->format('Y-m-d H:i')} — batch notified.");

            return self::SUCCESS;
        });
    }
}
