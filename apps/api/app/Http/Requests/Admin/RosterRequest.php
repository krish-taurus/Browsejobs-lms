<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Shared validation for roster operations (add / import / transfer / remove). */
final class RosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-rosters.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Silo add
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            // CSV import
            'csv' => ['sometimes', 'required', 'string', 'max:200000'],
            // Transfer
            'target_batch_id' => ['sometimes', 'required', 'integer'],
            // Transfer + remove
            'reason' => ['sometimes', 'required', 'string', 'max:500'],
        ];
    }
}
