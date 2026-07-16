<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TutorMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One turn of an AI Tutor conversation (PRD §6.4). `citations` are resolved and
 * attached by the CitationResolver before serialization.
 *
 * @mixin TutorMessage
 */
final class TutorMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'body' => $this->body,
            'confidence' => $this->confidence,
            'escalated' => (bool) $this->escalated,
            'ticket_id' => $this->ticket_id,
            'citations' => $this->getAttribute('citations') ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
