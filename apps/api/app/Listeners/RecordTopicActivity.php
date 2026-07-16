<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Events\TopicCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Records a `topic_completed` telemetry event (PRD §6.4). Queued; the domain
 * event is already fired by MarkTopicCompleted.
 */
final class RecordTopicActivity implements ShouldQueue
{
    public function __construct(private readonly RecordActivity $activity) {}

    public function handle(TopicCompleted $event): void
    {
        $this->activity->handle($event->user, ActivityType::TopicCompleted, $event->topic);
    }
}
