<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a student completes a topic. First-class per PRD §6.2; listeners
 * (progress, coach, notifications) are wired in later phases.
 */
final class TopicCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Topic $topic,
    ) {}
}
