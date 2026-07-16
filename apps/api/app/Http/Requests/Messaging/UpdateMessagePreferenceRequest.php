<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Enums\MessageChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMessagePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authenticated student updates their own preference.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferred_channel' => ['required', Rule::enum(MessageChannel::class)],
            'marketing_opt_in' => ['required', 'boolean'],
        ];
    }
}
