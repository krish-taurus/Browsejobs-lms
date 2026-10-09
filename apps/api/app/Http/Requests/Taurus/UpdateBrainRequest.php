<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Support\Taurus\BrainStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The console's "Brain & voice" form. Route middleware enforces
 * can:manage-settings; this only shapes the input. Blank secrets keep the
 * stored key (PlatformSettings::save); use DELETE …/providers/{id} to clear one.
 */
final class UpdateBrainRequest extends FormRequest
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
            'brain_provider' => ['sometimes', 'string', Rule::in(['platform', ...BrainStatus::PROVIDERS])],
            'brain_model' => ['sometimes', 'nullable', 'string', 'max:120'],

            'providers' => ['sometimes', 'array:'.implode(',', BrainStatus::PROVIDERS)],
            'providers.*' => ['array:api_key,model,base_url'],
            'providers.*.api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'providers.*.model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'providers.*.base_url' => ['sometimes', 'nullable', 'url:https,http', 'max:255'],

            'voice' => ['sometimes', 'array:api_key,voice_id,model'],
            'voice.api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'voice.voice_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'voice.model' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
