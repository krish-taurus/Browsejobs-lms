<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff putting a ticket's priority back after an AI raise (PRD §6.13). `priority` is
 * explicit rather than inferred — the human states what it should be, which is the
 * point of the undo.
 */
final class ClearAiPriorityRequest extends FormRequest
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
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ];
    }
}
