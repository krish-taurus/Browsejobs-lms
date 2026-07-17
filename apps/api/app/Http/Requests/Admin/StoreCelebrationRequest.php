<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCelebrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-messaging.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'display_mode' => ['required', Rule::in(['named', 'anonymous'])],
            'anonymous_label' => ['required_if:display_mode,anonymous', 'nullable', 'string', 'max:190'],
            'role_title' => ['required', 'string', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            // PRD §6.18: broadcast only with the student's explicit consent.
            'consent' => ['accepted'],
        ];
    }
}
