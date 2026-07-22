<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MockInterview;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when a mock interview is graded and closed (PRD §6.6) — the single
 * completion point for both text and voice mocks. Drives module-mock progress
 * and any future post-mock automations.
 */
final class MockCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly MockInterview $interview,
    ) {}
}
