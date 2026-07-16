<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

final class AssignLeadRequest extends FormRequest
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
            'counselor_id' => ['required', 'integer'],
        ];
    }
}
