<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EnrolmentType;
use App\Enums\EntitlementFeature;
use App\Events\ModuleCompleted;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Progression reward (PRD §6.6): completing a module unlocks one voice-mock
 * credit, capped at the tenant's included live quota (the 5-per-course quota
 * "doubles as a progression reward"). Queued — never inline in the request cycle.
 */
final class GrantModuleReward implements ShouldQueue
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function handle(ModuleCompleted $event): void
    {
        $student = $event->user;
        $tenant = $student->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student): void {
            $feature = EntitlementFeature::VoiceMock->value;
            $cap = $this->entitlements->includedQuota($feature, EnrolmentType::Live);

            if ($this->entitlements->balance($student, $feature) >= $cap) {
                return;
            }

            $this->entitlements->grantCredits($student, $feature, 1, 'module_reward');
        });
    }
}
