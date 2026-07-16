<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
final class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'category' => $this->category->value,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'first_response_due_at' => $this->first_response_due_at?->toIso8601String(),
            'resolution_due_at' => $this->resolution_due_at?->toIso8601String(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'reopenable' => $this->reopenable(),
            'breached' => $this->breached_at !== null || $this->resolution_breached_at !== null,
            'escalated' => $this->escalated_at !== null,
            'csat_rating' => $this->csat_rating,
            'created_at' => $this->created_at?->toIso8601String(),
            'student' => $this->whenLoaded('student', fn () => $this->student === null ? null : [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'role' => $this->assignee->roles->pluck('name')->first(),
            ]),
            'team' => $this->whenLoaded('supportTeam', fn () => $this->supportTeam?->name),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
