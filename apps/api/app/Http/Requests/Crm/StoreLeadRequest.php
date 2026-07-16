<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manual lead creation from the admin CRM (counselor enters a lead by hand).
 */
final class StoreLeadRequest extends FormRequest
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
            'lead_type' => ['required', Rule::in(Lead::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'email' => ['nullable', 'email', 'max:180'],
            'course_slug' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'utm_source' => ['nullable', 'string', 'max:120'],
        ];
    }
}
