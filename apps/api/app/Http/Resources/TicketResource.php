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

            // Triage signals are STAFF-ONLY (PRD §6.13). This resource also serves the
            // student's own "My Tickets", and showing a student our sentiment read of
            // them ("we scored you angry") would be both a betrayal and useless to
            // them — the same reason the PRD keeps AI-likelihood flags to trainer eyes
            // only. Gated on the viewer, not the route, so a future surface cannot leak
            // them by forgetting.
            'ai' => $this->when($this->viewerIsStaff($request), fn (): array => [
                'category' => $this->ai_category?->value,
                'urgency' => $this->ai_urgency?->value,
                'sentiment' => $this->ai_sentiment?->value,
                'priority_raised' => (bool) $this->ai_priority_raised,
                'duplicate_of_id' => $this->ai_duplicate_of_id,
                'triaged_at' => $this->ai_triaged_at?->toIso8601String(),
                // A suggestion is only worth showing when it disagrees with the human.
                'category_differs' => $this->ai_category !== null && $this->ai_category !== $this->category,
            ]),
        ];
    }

    private function viewerIsStaff(Request $request): bool
    {
        return $request->user()?->user_type === 'staff';
    }
}
