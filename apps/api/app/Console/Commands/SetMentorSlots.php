<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\MentorAvailability;
use App\Models\MentorProfile;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Sets a mentor's weekly availability from the CRM — replaces the whole
 * pattern in one shot (the mentor can still fine-tune it in their own LMS
 * hub). Slots are IST windows; students book 30-minute slots inside them.
 */
final class SetMentorSlots extends Command
{
    use ResolvesCrmTargets;

    /** Day-name → Carbon dayOfWeek (Sunday = 0). */
    private const DAYS = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    protected $signature = 'mentor:slots
        {user : Mentor staff user id or email}
        {--set= : Weekly windows, e.g. "Mon 18:00-20:00; Wed 18:00-20:00; Sat 10:00-13:00"}
        {--clear : Remove all availability (mentor becomes unbookable)}';

    protected $description = "Replace a mentor's weekly availability windows (IST)";

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');

        $user = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('user_type', 'staff')
            ->when(
                ctype_digit($identifier),
                fn ($q) => $q->whereKey((int) $identifier),
                fn ($q) => $q->where('email', $identifier),
            )
            ->first();

        $profile = $user === null ? null : MentorProfile::query()->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->first();

        if ($profile === null) {
            $this->error("Mentor '{$identifier}' not found — add them to the mentor pool first (mentor:set).");

            return self::FAILURE;
        }

        $windows = [];
        if (! $this->option('clear')) {
            foreach (preg_split('/[;,]/', (string) $this->option('set')) as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }

                if (! preg_match('/^([A-Za-z]{3,9})\s+(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $entry, $m)) {
                    $this->error("Could not parse '{$entry}' — use e.g. \"Mon 18:00-20:00\".");

                    return self::FAILURE;
                }

                $day = self::DAYS[strtolower(substr($m[1], 0, 3))] ?? null;
                $start = ((int) $m[2]) * 60 + (int) $m[3];
                $end = ((int) $m[4]) * 60 + (int) $m[5];

                if ($day === null || $end <= $start) {
                    $this->error("Invalid window '{$entry}'.");

                    return self::FAILURE;
                }

                $windows[] = ['weekday' => $day, 'start_minute' => $start, 'end_minute' => $end];
            }

            if ($windows === []) {
                $this->error('No windows given — pass --set="Mon 18:00-20:00; …" or --clear.');

                return self::FAILURE;
            }
        }

        return $this->runForTenant($profile->tenant_id, function () use ($profile, $windows): int {
            MentorAvailability::query()->where('mentor_profile_id', $profile->id)->delete();

            foreach ($windows as $window) {
                MentorAvailability::query()->create([
                    'tenant_id' => $profile->tenant_id,
                    'mentor_profile_id' => $profile->id,
                    ...$window,
                ]);
            }

            $this->info($windows === []
                ? 'Availability cleared — the mentor is unbookable until new slots are set.'
                : 'Weekly availability set: '.count($windows).' window(s). Students can book 30-min slots inside them (IST).');

            return self::SUCCESS;
        });
    }
}
