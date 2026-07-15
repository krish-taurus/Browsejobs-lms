<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Zoom\ZoomClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deletes the Zoom meeting for a cancelled session (queued Zoom API call).
 */
final class DeleteZoomMeeting implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $meetingId) {}

    public function handle(ZoomClient $zoom): void
    {
        $zoom->deleteMeeting($this->meetingId);
    }
}
