<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use App\Models\EmployerAutomationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PRD-E F6 guardrails live here first: the only actions are advance and
 * park; advance targets are limited to shortlisted/l1/l2. Rejection and
 * offer stages are structurally impossible to automate.
 */
final class StoreAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controller enforces managesPipeline() via ResolvesMembership.
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'trigger' => ['required', Rule::in([
                EmployerAutomationRule::TRIGGER_APPLICATION_GRADED,
                EmployerAutomationRule::TRIGGER_INTERVIEW_GRADED,
            ])],
            'round' => ['required_if:trigger,interview_graded', 'nullable', Rule::in(['l1', 'l2'])],
            'min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'action' => ['required', Rule::in([
                EmployerAutomationRule::ACTION_ADVANCE,
                EmployerAutomationRule::ACTION_PARK,
            ])],
            'target_stage' => [
                'required_if:action,advance',
                'nullable',
                Rule::in(EmployerAutomationRule::ALLOWED_TARGET_STAGES),
            ],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}
