<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CrmTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmTask
 */
final class CrmTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'title' => $this->title,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'source' => $this->source,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead === null ? null : [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'phone' => $this->lead->phone,
            ]),
        ];
    }
}
