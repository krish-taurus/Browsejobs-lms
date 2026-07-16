<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Models\TicketRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTicketRouteRequest extends FormRequest
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
            'routing' => ['sometimes', 'required', Rule::in([
                TicketRoute::ROUTING_TEAM, TicketRoute::ROUTING_BATCH_TRAINER, TicketRoute::ROUTING_ADMIN,
            ])],
            'support_team_id' => ['nullable', 'integer'],
            'first_response_minutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'resolution_minutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
