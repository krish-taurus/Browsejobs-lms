<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
final class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_type' => $this->lead_type,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'course_slug' => $this->course_slug,
            'message' => $this->message,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'score' => $this->score,
            'lead_stage_id' => $this->lead_stage_id,
            'stage' => $this->whenLoaded('stage', fn () => $this->stage === null ? null : new LeadStageResource($this->stage)),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'first_touch_at' => $this->first_touch_at?->toIso8601String(),
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'sla_breached_at' => $this->sla_breached_at?->toIso8601String(),
            'merged_into_id' => $this->merged_into_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'timeline' => ContactTimelineEventResource::collection($this->whenLoaded('timelineEvents')),
            'tasks' => CrmTaskResource::collection($this->whenLoaded('tasks')),
            'duplicates' => $this->when(isset($this->duplicates), fn () => LeadResource::collection($this->duplicates)),
        ];
    }
}
