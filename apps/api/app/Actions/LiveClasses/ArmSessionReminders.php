<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Jobs\SendSessionReminder;
use App\Models\LiveSession;
use Illuminate\Support\Str;

/**
 * Schedules the 12h/2h/5min reminder ladder for a session. Each session carries
 * a `reminder_token`; arming issues a fresh token and dispatches delayed jobs
 * stamped with it. Re-arming (reschedule) issues a new token, so previously
 * queued jobs — which still hold the old token — become no-ops. Offsets already
 * in the past are skipped.
 *
 * @return string the token the reminders were armed with
 */
final readonly class ArmSessionReminders
{
    public function handle(LiveSession $session): string
    {
        $token = (string) Str::uuid();
        $session->update(['reminder_token' => $token]);

        /** @var list<array{minutes: int, label: string}> $offsets */
        $offsets = config('live_classes.reminder_offsets', []);

        foreach ($offsets as $offset) {
            $fireAt = $session->scheduled_start->copy()->subMinutes($offset['minutes']);

            if ($fireAt->isPast()) {
                continue;
            }

            SendSessionReminder::dispatch($session->id, $token, $offset['label'])->delay($fireAt);
        }

        return $token;
    }
}
