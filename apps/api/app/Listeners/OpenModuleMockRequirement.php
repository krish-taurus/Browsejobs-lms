<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ModuleCompleted;
use App\Models\InAppNotification;
use App\Models\ModuleMockRequirement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On ModuleCompleted, open the student's mock quota for that module (PRD §6.6
 * "N mocks per module"): N comes from the module's `required_mocks` override or
 * the tenant/config default. Idempotent — one requirement per student+module,
 * so re-completing never reopens or resets progress. Queued.
 */
final class OpenModuleMockRequirement implements ShouldQueue
{
    public function handle(ModuleCompleted $event): void
    {
        $student = $event->user;
        $module = $event->module;
        $tenant = $student->tenant;

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student, $module): void {
            $required = max(1, $module->required_mocks ?? (int) config('mocks.per_module', 3));

            $requirement = ModuleMockRequirement::query()->firstOrCreate(
                ['user_id' => $student->id, 'module_id' => $module->id],
                ['tenant_id' => $student->tenant_id, 'required' => $required, 'completed' => 0, 'unlocked_at' => now()],
            );

            if (! $requirement->wasRecentlyCreated) {
                return; // already open — don't reset progress or re-notify
            }

            InAppNotification::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'title' => "Mocks unlocked — {$module->name}.",
                'body' => "Clear {$required} AI mock interview".($required === 1 ? '' : 's').' for this module to keep progressing.',
                'url' => '/mock',
            ]);
        });
    }
}
