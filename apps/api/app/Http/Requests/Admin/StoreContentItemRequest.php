<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ContentHubItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreContentItemRequest extends FormRequest
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
            'kind' => ['required', Rule::in(ContentHubItem::KINDS)],
            'title' => ['required', 'string', 'max:190'],
            'url' => ['required', 'url', 'max:500'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
