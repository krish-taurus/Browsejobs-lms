<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Jobs\CreateZoomMeeting;
use App\Models\Batch;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

it('re-queues Zoom creation only for future scheduled classes missing a meeting', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        $course = Course::factory()->for($tenant)->create();
        $batch = Batch::factory()->for($tenant)->create([
            'course_id' => $course->id, 'type' => BatchType::Paid->value, 'status' => 'active',
        ]);

        // Missing meeting, future → should be queued.
        $this->needsZoom = LiveSession::query()->create([
            'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'kind' => 'class',
            'title' => 'No Zoom Yet', 'scheduled_start' => now()->addDay(), 'status' => 'scheduled',
        ]);

        // Already has a meeting → untouched.
        LiveSession::query()->create([
            'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'kind' => 'class',
            'title' => 'Has Zoom', 'scheduled_start' => now()->addDays(2), 'status' => 'scheduled',
            'zoom_meeting_id' => 'zm-1', 'zoom_join_url' => 'https://zoom.us/j/1',
        ]);

        // Past class → untouched.
        LiveSession::query()->create([
            'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'kind' => 'class',
            'title' => 'Old Class', 'scheduled_start' => now()->subDay(), 'status' => 'scheduled',
        ]);
    });

    $this->artisan('zoom:backfill')->assertSuccessful();

    Queue::assertPushed(CreateZoomMeeting::class, 1);
    Queue::assertPushed(CreateZoomMeeting::class, fn ($job) => $job->liveSessionId === $this->needsZoom->id);
});
