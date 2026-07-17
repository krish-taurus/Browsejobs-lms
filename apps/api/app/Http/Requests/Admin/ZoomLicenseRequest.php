<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or update a Zoom host license and its mentor allocation (PRD §6.3).
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
            'mentor_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
