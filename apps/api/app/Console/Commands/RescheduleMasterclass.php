<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Batch;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Moves a masterclass to a new date/time: shifts the batch dates and
 * reschedules its class — Zoom update, old-vs-new-time notifications to every
 * enrolled student and batch staff, change log, and a fresh reminder ladder,
 * all via RescheduleLiveSession.
 */
final class RescheduleMasterclass extends Command
{
    protected $signature = 'masterclass:reschedule
        {batch : Batch id or number}
        {date : New class date (Y-m-d)}
        {time : New start time (HH:MM, 24h)}
        {--duration=90 : Length in minutes}
        {--reason=Rescheduled by the team : Shown in the notification}';

    protected $description = 'Move a masterclass batch (and its class, with notifications) to a new date/time';

    public function handle(
        RescheduleLiveSession $reschedule,
        ScheduleLiveSession $scheduleSession,
        TenantContext $tenants,
    ): int {
        $batch = $this->findMasterclassBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Masterclass batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($batch->tenant_id);
        $start = Carbon::parse($this->argument('date').' '.$this->argument('time'));
        $end = $start->copy()->addMinutes((int) $this->option('duration'));

        return $tenants->run($tenant, function () use ($batch, $start, $end, $reschedule, $scheduleSession): int {
            $batch->update([
                'starts_on' => $start->toDateString(),
                'ends_on' => $start->toDateString(),
            ]);

            $session = $batch->liveSessions()
                ->where('status', LiveSessionStatus::Scheduled->value)
                ->first();

            if ($session !== null) {
                $reschedule->handle($session, $start, $end, (string) $this->option('reason'));
                $this->info("Rescheduled {$batch->number} to {$start} — students and trainers notified.");
            } else {
                $session = $scheduleSession->handle($batch, 'Masterclass — '.($batch->course?->name ?? $batch->number), $start, $end);
                $this->info("Batch {$batch->number} had no class yet — scheduled one at {$start} (session #{$session->id}).");
            }

            return self::SUCCESS;
        });
    }

    private function findMasterclassBatch(string $key): ?Batch
    {
        return Batch::query()->withoutGlobalScope(TenantScope::class)
            ->where('type', BatchType::Masterclass->value)
            ->where(fn ($q) => is_numeric($key) ? $q->whereKey((int) $key)->orWhere('number', $key) : $q->where('number', $key))
            ->first();
    }
}
