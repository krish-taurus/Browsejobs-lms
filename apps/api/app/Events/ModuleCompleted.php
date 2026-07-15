<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a student completes every topic in a module. First-class per
 * PRD §6.2; drives MCQ dispatch and coach automations in later phases.
 */
final class ModuleCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Module $module,
    ) {}
}
