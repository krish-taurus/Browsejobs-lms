<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Actions\Employers\MoveApplicationStage;
use App\Enums\EmployerApplicationStage;
use App\Models\EmployerAutomationRule;
use App\Models\EmployerJobApplication;

/**
 * Shared rule evaluation (PRD-E F6). Called from queued listeners only.
 *
 * Guardrails re-checked at evaluation time (defence in depth beyond
 * request validation): the target stage must be in the allowlist and
 * the transition must be legal — otherwise the run records a skip
 * instead of forcing the move. Park never changes stage; it exists so
 * an employer can route sub-threshold candidates to a review queue
 * without auto-rejecting anyone (automation never rejects).
 */
final readonly class AutomationEvaluator
{
    public function __construct(private MoveApplicationStage $move) {}

    public function evaluate(EmployerAutomationRule $rule, EmployerJobApplication $application, int $score): void
    {
        if ($score < $rule->min_score) {
            $this->record($rule, $application, 'skipped_below_threshold', $score, $application->cv_match_pct);

            return;
        }

        // A CV-match floor is optional on a rule (hand-written rules from
        // before this existed simply don't set one) but, when set, is a hard
        // gate same as the score — a great interview off a thin or
        // mismatched CV shouldn't reach an employer's shortlist on talk
        // alone. No recorded match (older applications, or a trigger this
        // isn't computed for) fails safe rather than passing silently.
        if ($rule->min_cv_match_pct !== null
            && ($application->cv_match_pct === null || $application->cv_match_pct < $rule->min_cv_match_pct)) {
            $this->record($rule, $application, 'skipped_below_threshold', $score, $application->cv_match_pct);

            return;
        }

        if ($rule->action === EmployerAutomationRule::ACTION_PARK) {
            $this->record($rule, $application, 'applied', $score, $application->cv_match_pct);

            return;
        }

        $target = EmployerApplicationStage::tryFrom((string) $rule->target_stage);

        if ($target === null
            || ! in_array($target->value, EmployerAutomationRule::ALLOWED_TARGET_STAGES, true)
            || ! $application->stage->canAdvanceTo($target)) {
            $this->record($rule, $application, 'skipped_invalid_transition', $score);

            return;
        }

        $note = $rule->min_cv_match_pct !== null
            ? "Automation: score {$score} ≥ {$rule->min_score}, CV match {$application->cv_match_pct} ≥ {$rule->min_cv_match_pct}"
            : "Automation: score {$score} ≥ {$rule->min_score}";

        $this->move->handle($application, $target, null, $note, 'rule');

        $this->record($rule, $application, 'applied', $score, $application->cv_match_pct);
    }

    private function record(EmployerAutomationRule $rule, EmployerJobApplication $application, string $outcome, int $score, ?int $cvMatchPct): void
    {
        $rule->runs()->create([
            'employer_job_application_id' => $application->id,
            'action' => $rule->action,
            'outcome' => $outcome,
            'score_seen' => max(0, min(100, $score)),
            'cv_match_seen' => $cvMatchPct !== null ? max(0, min(100, $cvMatchPct)) : null,
            'occurred_at' => now(),
        ]);
    }
}
