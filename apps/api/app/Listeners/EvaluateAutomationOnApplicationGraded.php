<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApplicationGraded;
use App\Models\EmployerAutomationRule;
use App\Support\Employers\AutomationEvaluator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * PRD-E F6: JD-mock grading arrived → evaluate application_graded rules
 * (the "auto-shortlist at ≥ X%" case). Queued, idempotent per firing —
 * MoveApplicationStage refuses repeat transitions and the run log makes
 * every decision inspectable.
 */
final class EvaluateAutomationOnApplicationGraded implements ShouldQueue
{
    public function __construct(private readonly AutomationEvaluator $evaluator) {}

    public function handle(ApplicationGraded $event): void
    {
        $application = $event->application;

        app(TenantContext::class)->run($application->tenant, function () use ($application): void {
            $rules = EmployerAutomationRule::query()
                ->where('employer_job_id', $application->employer_job_id)
                ->where('trigger', EmployerAutomationRule::TRIGGER_APPLICATION_GRADED)
                ->where('enabled', true)
                ->get();

            foreach ($rules as $rule) {
                $this->evaluator->evaluate($rule, $application->fresh(), (int) $application->mock_score);
            }
        });
    }
}
