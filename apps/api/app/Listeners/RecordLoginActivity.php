<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Records a `login` telemetry event for students (PRD §6.4). Queued; ignores staff.
 */
final class RecordLoginActivity implements ShouldQueue
{
    public function __construct(private readonly RecordActivity $activity) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->user_type !== 'student' || $user->tenant === null) {
            return;
        }

        $this->activity->handle($user, ActivityType::Login);
    }
}
