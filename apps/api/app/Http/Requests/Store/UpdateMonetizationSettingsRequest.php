<?php

declare(strict_types=1);

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMonetizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-monetization.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cv_free_grants' => ['sometimes', 'integer', 'min:0'],
            'voice_included_live' => ['sometimes', 'integer', 'min:0'],
            'voice_included_self_paced' => ['sometimes', 'integer', 'min:0'],
            'self_paced_pct_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'text_practice_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
