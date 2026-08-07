<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\AnnounceClassScheduled;
use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Models\Batch;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Manually adds a class to any batch (bootcamp / paid / masterclass): schedules
 * the live session (Zoom meeting + 12h/2h/5min reminder ladder via
 * ScheduleLiveSession) and immediately announces it to every occupying student
 * on WhatsApp + email. Driven by the CRM's Live Batches page as well as usable
 * by hand.
 */
final class ScheduleClass extends Command
{
    protected $signature = 'class:schedule
        {batch : Batch id or batch number (e.g. DE-202608-01)}
        {date : Class date (Y-m-d)}
        {time : Start time (HH:MM, 24h)}
        {--duration=90 : Length in minutes}
        {--title= : Class title (defaults to "Class — <course>")}
        {--kind=class : class, or mentoring (weekly batch mentoring session)}
        {--host= : Host staff user id or email (e.g. the allocated mentor)}
        {--no-announce : Skip the instant student announcement}';

    protected $description = 'Schedule a class for a batch (Zoom + reminders) and notify every student in it';

    public function handle(
        ScheduleLiveSession $scheduleSession,
        AnnounceClassScheduled $announce,
        TenantContext $tenants,
    ): int {
        $identifier = (string) $this->argument('batch');

        $batch = Batch::query()->withoutGlobalScope(TenantScope::class)
            ->when(
                ctype_digit($identifier),
                fn ($q) => $q->whereKey((int) $identifier),
                fn ($q) => $q->where('number', $identifier),
            )
            ->first();

        if ($batch === null) {
            $this->error("Batch '{$identifier}' not found.");

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($batch->tenant_id);
        if ($tenant === null) {
            $this->error('Batch has no tenant.');

            return self::FAILURE;
        }

        $start = Carbon::parse($this->argument('date').' '.$this->argument('time'));
        $end = $start->copy()->addMinutes((int) $this->option('duration'));

        if ($start->isPast()) {
            $this->error('Class time is in the past — pick a future slot.');

            return self::FAILURE;
        }

        $kind = (string) $this->option('kind');
        if (! in_array($kind, ['class', 'mentoring'], true)) {
            $this->error("Unknown kind '{$kind}' — use class or mentoring.");

            return self::FAILURE;
        }

        // The allocated mentor/trainer hosting this session (batch mentoring).
        $hostUserId = null;
        if ($this->option('host')) {
            $hostIdentifier = (string) $this->option('host');
            $host = User::query()->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $batch->tenant_id)
                ->where('user_type', 'staff')
                ->when(
                    ctype_digit($hostIdentifier),
                    fn ($q) => $q->whereKey((int) $hostIdentifier),
                    fn ($q) => $q->where('email', $hostIdentifier),
                )
                ->first();

            if ($host === null) {
                $this->error("Host staff user '{$hostIdentifier}' not found.");

                return self::FAILURE;
            }

            $hostUserId = $host->id;
        }

        return $tenants->run($tenant, function () use ($batch, $start, $end, $kind, $hostUserId, $scheduleSession, $announce): int {
            $batch->load('course');

            $defaultTitle = ($kind === 'mentoring' ? 'Mentoring — ' : 'Class — ').($batch->course?->name ?? $batch->number);

            $session = $scheduleSession->handle(
                $batch,
                (string) ($this->option('title') ?: $defaultTitle),
                $start,
                $end,
                kind: $kind,
                hostUserId: $hostUserId,
            );

            $this->info("Class scheduled: {$session->title} at {$session->scheduled_start} (session #{$session->id}) for batch {$batch->number}.");

            if (! $this->option('no-announce')) {
                $notified = $announce->handle($session);
                $this->info("Announced to {$notified} student(s) on WhatsApp + email.");
            }

            return self::SUCCESS;
        });
    }
}
