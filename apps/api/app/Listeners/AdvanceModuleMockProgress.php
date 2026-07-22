<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MockCompleted;
use App\Models\InAppNotification;
use App\Models\ModuleMockRequirement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On MockCompleted, credit the mock to the student's earliest open module
 * requirement (FIFO), and clear that module's mock gate once its target is met
 * (PRD §6.6). Mocks are course-level, so a finished mock counts toward whichever
 * unlocked module still needs mocks — oldest first. A mock finished with no open
 * requirement is a no-op. Queued.
 */
final class AdvanceModuleMockProgress implements ShouldQueue
{
    public function handle(MockCompleted $event): void
    {
        $student = $event->user;
        $tenant = $student->tenant;

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student): void {
            $requirement = ModuleMockRequirement::query()
                ->where('user_id', $student->id)
                ->whereNull('cleared_at')
                ->whereColumn('completed', '<', 'required')
                ->orderBy('unlocked_at')
                ->orderBy('id')
                ->first();

            if ($requirement === null) {
                return;
            }

            $requirement->completed++;

            if ($requirement->completed >= $requirement->required) {
                $requirement->cleared_at = now();
            }
            $requirement->save();

            if ($requirement->cleared_at !== null) {
                $module = $requirement->module;
                InAppNotification::query()->create([
                    'tenant_id' => $student->tenant_id,
                    'user_id' => $student->id,
                    'title' => 'Module mocks cleared'.($module !== null ? " — {$module->name}." : '.'),
                    'body' => 'You finished every mock this module needed. On to the next.',
                    'url' => '/mock',
                ]);
            }
        });
    }
}
