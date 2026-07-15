<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\LiveSessionStatus;
use App\Jobs\CreateZoomMeeting;
use App\Models\Batch;
use App\Models\LiveSession;
use App\Models\Topic;
use Carbon\CarbonInterface;

/**
 * Schedules a live class for a batch and queues creation of its Zoom meeting.
 * The Zoom API call is deferred to a job (CLAUDE.md) so scheduling stays fast
 * and retry-safe.
 */
final readonly class ScheduleLiveSession
{
    public function __construct(private ArmSessionReminders $armReminders) {}

    public function handle(
        Batch $batch,
        string $title,
        CarbonInterface $start,
        ?CarbonInterface $end = null,
        ?Topic $topic = null,
    ): LiveSession {
        $session = LiveSession::query()->create([
            'tenant_id' => $batch->tenant_id,
            'batch_id' => $batch->id,
            'topic_id' => $topic?->id,
            'title' => $title,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => LiveSessionStatus::Scheduled->value,
        ]);

        CreateZoomMeeting::dispatch($session->id);

        $this->armReminders->handle($session);

        return $session;
    }
}
