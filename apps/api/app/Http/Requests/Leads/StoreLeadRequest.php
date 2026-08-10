<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_type' => ['required', Rule::in(Lead::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'course_slug' => ['nullable', 'string', 'max:120'],
            // Employer enquiries (lead_type=employer) name the hiring company.
            'company' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:2000'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:190'],
            'page' => ['nullable', 'string', 'max:190'],
            // DPDP: explicit consent is mandatory on every lead form.
            'consent' => ['accepted'],
        ];
    }
}
