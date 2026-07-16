<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Events\ModuleCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Records a `module_completed` telemetry event (PRD §6.4). Queued.
 */
final class RecordModuleActivity implements ShouldQueue
{
    public function __construct(private readonly RecordActivity $activity) {}

    public function handle(ModuleCompleted $event): void
    {
        $this->activity->handle($event->user, ActivityType::ModuleCompleted, $event->module);
    }
}
