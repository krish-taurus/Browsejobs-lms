<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmployerInterviewGraded;
use App\Models\EmployerAutomationRule;
use App\Support\Employers\AutomationEvaluator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * PRD-E F6: an L1/L2 round was graded → evaluate interview_graded rules
 * for that round. This is the same-day chain: "L1 ≥ 75% → advance to
 * L2" fires here, and entering L2 auto-creates the next invite via
 * CreateInterviewOnStageEntry.
 */
final class EvaluateAutomationOnInterviewGraded implements ShouldQueue
{
    public function __construct(private readonly AutomationEvaluator $evaluator) {}

    public function handle(EmployerInterviewGraded $event): void
    {
        $interview = $event->interview;

        app(TenantContext::class)->run($interview->tenant, function () use ($interview): void {
            $application = $interview->application()->withoutGlobalScopes()->first();
            if ($application === null) {
                return;
            }

            $rules = EmployerAutomationRule::query()
                ->where('employer_job_id', $application->employer_job_id)
                ->where('trigger', EmployerAutomationRule::TRIGGER_INTERVIEW_GRADED)
                ->where('round', $interview->round)
                ->where('enabled', true)
                ->get();

            foreach ($rules as $rule) {
                $this->evaluator->evaluate($rule, $application->fresh(), (int) $interview->overall_score);
            }
        });
    }
}
