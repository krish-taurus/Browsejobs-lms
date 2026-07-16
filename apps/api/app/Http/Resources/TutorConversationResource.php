<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TutorConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An AI Tutor conversation (PRD §6.4). The thread view loads `messages`; the list
 * view exposes just the summary.
 *
 * @mixin TutorConversation
 */
final class TutorConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'lesson_id' => $this->lesson_id,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'message_count' => $this->whenCounted('messages'),
            'messages' => TutorMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
