<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or update a Zoom host license (PRD §6.3). Licenses auto-rotate across
 * whoever is teaching (ADR 0043), so there is no per-person allocation here.
 */
final class ZoomLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware enforces can:manage-batches
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'zoom_user_id' => ['required', 'string', 'max:200'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
