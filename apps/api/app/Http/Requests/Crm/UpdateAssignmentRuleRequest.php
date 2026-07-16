<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Models\CrmAssignmentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAssignmentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-leads.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in([CrmAssignmentRule::MODE_ROUND_ROBIN, CrmAssignmentRule::MODE_BY_COURSE])],
            'sla_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'by_course_map' => ['nullable', 'array'],
            'by_course_map.*' => ['integer'],
        ];
    }
}
