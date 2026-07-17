<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Enums\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The draft concern a student is about to raise (PRD §6.13), sent to the AI
 * first-response layer before the ticket exists. Deliberately a subset of
 * `StoreTicketRequest`: no subject, no attachments, no priority — nothing that only
 * matters once a ticket is real.
 */
final class DeflectTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'body' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
