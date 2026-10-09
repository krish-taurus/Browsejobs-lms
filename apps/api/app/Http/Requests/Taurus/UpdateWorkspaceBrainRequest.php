<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Models\TaurusWorkspace;
use App\Support\Taurus\BrainStatus;
use App\Support\Taurus\WorkspaceBrain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A workspace's "Brain & voice" form. `platform` is a valid brain only for
 * the owner workspace (Taurus HQ); a client workspace choosing it is a 422.
 * Blank keys keep the stored one; DELETE …/brain/providers/{id} clears one.
 */
final class UpdateWorkspaceBrainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // taurus.member:manage enforces owner/admin
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');
        $allowsPlatform = $workspace instanceof TaurusWorkspace && WorkspaceBrain::allowsPlatform($workspace);
        $choices = $allowsPlatform ? ['platform', ...BrainStatus::PROVIDERS] : BrainStatus::PROVIDERS;

        return [
            'brain_provider' => ['sometimes', 'nullable', 'string', Rule::in($choices)],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brain_provider.in' => 'Choose one of your own providers. Only Taurus HQ can use the platform brain.',
        ];
    }
}
