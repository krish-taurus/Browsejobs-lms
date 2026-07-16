<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Enums\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CannedResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:handle-tickets.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', Rule::enum(TicketCategory::class)],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
