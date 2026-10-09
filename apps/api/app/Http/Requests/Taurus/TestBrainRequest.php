<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Support\Taurus\BrainStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Which saved key to prove with one live call: an LLM provider or ElevenLabs. */
final class TestBrainRequest extends FormRequest
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
            'target' => ['required', 'string', Rule::in([...BrainStatus::PROVIDERS, 'elevenlabs'])],
        ];
    }
}
