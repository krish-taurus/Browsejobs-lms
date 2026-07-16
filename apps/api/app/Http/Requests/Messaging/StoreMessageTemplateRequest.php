<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-messaging.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:80'],
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'category' => ['required', Rule::enum(MessageCategory::class)],
            'name' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:4000'],
            'locale' => ['nullable', 'string', 'max:8'],
            'active' => ['boolean'],
        ];
    }
}
