<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use App\Models\EmployerJobRound;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The designed interview process for one JD (PRD-E F18).
 *
 * The rule worth reading is the last one: an automatic round must carry a
 * threshold. "Automatic with no bar" would interview every applicant the
 * moment they were graded, which is the opposite of what the setting means
 * and expensive in a way the employer would only discover afterwards.
 */
final class SaveInterviewProcessRequest extends FormRequest
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
            'rounds' => ['present', 'array', 'max:8'],
            'rounds.*.key' => ['nullable', 'string', 'max:32'],
            'rounds.*.name' => ['required', 'string', 'max:120'],
            'rounds.*.kind' => ['required', Rule::in(EmployerJobRound::KINDS)],
            'rounds.*.focus_skills' => ['nullable', 'array', 'max:12'],
            'rounds.*.focus_skills.*' => ['string', 'max:60'],
            'rounds.*.competency_weights' => ['nullable', 'array'],
            'rounds.*.competency_weights.*' => ['integer', 'min:0', 'max:100'],
            'rounds.*.format_mix' => ['nullable', 'array'],
            'rounds.*.format_mix.*' => ['integer', 'min:0', 'max:100'],
            'rounds.*.question_count' => ['nullable', 'integer', 'min:4', 'max:20'],
            'rounds.*.notes' => ['nullable', 'string', 'max:800'],
            'rounds.*.window_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'rounds.*.dispatch' => ['required', Rule::in(EmployerJobRound::DISPATCHES)],
            'rounds.*.auto_min_score' => [
                'required_if:rounds.*.dispatch,'.EmployerJobRound::DISPATCH_AUTO,
                'nullable', 'integer', 'min:1', 'max:100',
            ],
            'rounds.*.enabled' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rounds.*.auto_min_score.required_if' => 'An automatic round needs a score to fire at, or it would interview everyone.',
        ];
    }
}
