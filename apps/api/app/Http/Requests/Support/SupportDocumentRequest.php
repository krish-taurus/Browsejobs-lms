<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Enums\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A support policy document (PRD §6.13) — the corpus AI deflection and reply drafts
 * answer from. `category` is required, unlike the tutor's documents: an uncategorised
 * policy is unreachable, because retrieval always scopes to the ticket's category.
 */
final class SupportDocumentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
