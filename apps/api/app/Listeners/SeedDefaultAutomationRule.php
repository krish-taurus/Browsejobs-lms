<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EmployerApplicationStage;
use App\Events\EmployerJobPublished;
use App\Models\EmployerAutomationRule;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * PRD-E F6 extension (Aug 2026, candidate request): auto-shortlisting
 * shouldn't require an employer to find and configure the Automation tab —
 * every JD gets a sensible default rule the moment it's published, using
 * the platform-wide bar from monetization_settings (CRM-editable, no
 * deploy). An employer still sees it on their Automation tab like any other
 * rule (`is_default` just labels it there) and can edit or disable it same
 * as one they'd have written by hand.
 *
 * `min_cv_match_pct` is set here too — the interview-score-only guardrail
 * that already existed for hand-written rules doesn't apply to this one,
 * since a platform default with no CV-quality floor would shortlist anyone
 * who happened to ace the interview off a thin or mismatched CV.
 */
final class SeedDefaultAutomationRule implements ShouldQueue
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function handle(EmployerJobPublished $event): void
    {
        $job = $event->job;

        app(TenantContext::class)->run($job->tenant, function () use ($job): void {
            $settings = $this->entitlements->settings();

            if (! $settings->auto_shortlist_enabled) {
                return;
            }

            // Idempotent: a job can publish more than once (draft → published
            // → paused → published again) without this seeding a second
            // default rule each time.
            $alreadySeeded = EmployerAutomationRule::query()
                ->where('employer_job_id', $job->id)
                ->where('is_default', true)
                ->exists();

            if ($alreadySeeded) {
                return;
            }

            EmployerAutomationRule::query()->create([
                'tenant_id' => $job->tenant_id,
                'employer_job_id' => $job->id,
                'created_by_id' => $job->created_by_id,
                'trigger' => EmployerAutomationRule::TRIGGER_APPLICATION_GRADED,
                'round' => null,
                'min_score' => $settings->auto_shortlist_min_score,
                'min_cv_match_pct' => $settings->auto_shortlist_min_cv_match_pct,
                'action' => EmployerAutomationRule::ACTION_ADVANCE,
                'target_stage' => EmployerApplicationStage::Shortlisted->value,
                'enabled' => true,
                'is_default' => true,
            ]);
        });
    }
}
