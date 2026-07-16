<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContactTimelineEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactTimelineEvent
 */
final class ContactTimelineEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $this->body,
            'meta' => $this->meta,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ]),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
