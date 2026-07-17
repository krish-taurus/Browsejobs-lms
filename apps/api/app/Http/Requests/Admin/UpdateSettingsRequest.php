<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Save platform integration settings (super-admin only, enforced by the route gate).
 * The service validates each key against the whitelist, so this only shapes the input:
 * a `settings` map of group => key => value.
 */
final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware enforces can:manage-settings
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
        ];
    }
}
