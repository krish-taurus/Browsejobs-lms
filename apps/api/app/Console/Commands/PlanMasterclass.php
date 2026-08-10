<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Batches\CreateBatch;
use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Plans a masterclass in advance: the batch for a given course + date, plus its
 * live class at a concrete time slot (Zoom + reminder ladder handled by
 * ScheduleLiveSession). Idempotent: reuses an existing active batch for the
 * date, and won't double-schedule a class. Driven by the CRM's Masterclasses
 * page as well as usable by hand.
 */
final class PlanMasterclass extends Command
{
    protected $signature = 'masterclass:plan
        {course : Course slug}
        {date : Class date (Y-m-d)}
        {time : Start time (HH:MM, 24h)}
        {--duration=90 : Length in minutes}
        {--capacity= : Seat cap for the batch}
        {--title= : Class title (defaults to "Masterclass — <course>")}';

    protected $description = 'Create (or complete) a masterclass batch for a course with its scheduled class slot';

    public function handle(
        CreateBatch $createBatch,
        ScheduleLiveSession $scheduleSession,
        TenantContext $tenants,
    ): int {
        $course = Course::query()->withoutGlobalScope(TenantScope::class)
            ->where('slug', $this->argument('course'))
            ->first();

        if ($course === null) {
            $this->error("Course '{$this->argument('course')}' not found.");

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($course->tenant_id);
        if ($tenant === null) {
            $this->error('Course has no tenant.');

            return self::FAILURE;
        }

        $date = Carbon::parse($this->argument('date'))->startOfDay();
        $start = Carbon::parse($this->argument('date').' '.$this->argument('time'));
        $end = $start->copy()->addMinutes((int) $this->option('duration'));

        return $tenants->run($tenant, function () use ($course, $date, $start, $end, $createBatch, $scheduleSession): int {
            $batch = Batch::query()
                ->where('course_id', $course->id)
                ->where('type', BatchType::Masterclass->value)
                ->whereDate('starts_on', $date->toDateString())
                ->where('status', 'active')
                ->first();

            if ($batch === null) {
                $batch = $createBatch->handle($course, BatchType::Masterclass, [
                    'starts_on' => $date->toDateString(),
                    'ends_on' => $date->toDateString(),
                    'capacity' => $this->option('capacity') !== null ? (int) $this->option('capacity') : null,
                ]);
                $this->info("Created masterclass batch {$batch->number}.");
            } else {
                $this->info("Using existing batch {$batch->number}.");
            }

            $existing = $batch->liveSessions()
                ->where('status', LiveSessionStatus::Scheduled->value)
                ->first();

            if ($existing !== null) {
                $this->warn("Batch already has a class at {$existing->scheduled_start} — use masterclass:reschedule to move it.");

                return self::SUCCESS;
            }

            $session = $scheduleSession->handle(
                $batch,
                (string) ($this->option('title') ?: 'Masterclass — '.$course->name),
                $start,
                $end,
                kind: LiveSession::KIND_MASTERCLASS,
            );

            $this->info("Class scheduled: {$session->title} at {$session->scheduled_start} (session #{$session->id}).");

            return self::SUCCESS;
        });
    }
}
