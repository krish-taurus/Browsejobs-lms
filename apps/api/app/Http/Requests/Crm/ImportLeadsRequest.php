<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CSV lead import. Accepts either raw CSV text (`csv`) or an uploaded file.
 */
final class ImportLeadsRequest extends FormRequest
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
            'csv' => ['required_without:file', 'nullable', 'string'],
            'file' => ['required_without:csv', 'nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
