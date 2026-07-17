<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LiveSessionStatus;
use App\Enums\RecordingStatus;
use App\Models\Batch;
use App\Models\LiveSession;
use App\Models\Recording;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes the admin class schedule (PRD §6.3) demo-able on the seeded paid batch: one
 * upcoming class, and one that already ran with a recording attached — so the
 * "recording lands in the batch's library" story is visible.
 *
 * Rows are inserted directly rather than through ScheduleLiveSession so seeding does not
 * dispatch real Zoom jobs.
 */
class LiveClassSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $batch = Batch::query()->where('number', 'DE-202607-PAY')->first();
            if ($batch === null) {
                return;
            }

            LiveSession::query()->firstOrCreate(
                ['batch_id' => $batch->id, 'title' => 'SQL — Window functions'],
                [
                    'tenant_id' => $tenant->id,
                    'scheduled_start' => now()->addDays(2)->setTime(19, 0),
                    'scheduled_end' => now()->addDays(2)->setTime(20, 30),
                    'status' => LiveSessionStatus::Scheduled->value,
                    'reminder_token' => 'seed-upcoming',
                ],
            );

            $past = LiveSession::query()->firstOrCreate(
                ['batch_id' => $batch->id, 'title' => 'Python — Pandas basics'],
                [
                    'tenant_id' => $tenant->id,
                    'scheduled_start' => now()->subDays(3)->setTime(19, 0),
                    'scheduled_end' => now()->subDays(3)->setTime(20, 30),
                    'status' => LiveSessionStatus::Ended->value,
                    'reminder_token' => 'seed-past',
                ],
            );

            Recording::query()->firstOrCreate(
                ['live_session_id' => $past->id, 'title' => 'Python — Pandas basics'],
                [
                    'tenant_id' => $tenant->id,
                    'storage_path' => 'recordings/'.$tenant->id.'/'.$past->id.'/demo.mp4',
                    'duration_seconds' => 5400,
                    'status' => RecordingStatus::Stored->value,
                ],
            );
        });
    }
}
