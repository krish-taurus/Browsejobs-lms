<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LiveSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A live class for the admin schedule view (PRD §6.3). Raw Zoom URLs are never exposed
 * — `has_meeting` only says whether the Zoom meeting has been created yet.
 *
 * @mixin LiveSession
 */
final class LiveSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'scheduled_start' => $this->scheduled_start?->toIso8601String(),
            'scheduled_end' => $this->scheduled_end?->toIso8601String(),
            'status' => $this->status->value,
            'auto_record' => (bool) $this->auto_record,
            'topic' => $this->whenLoaded('topic', fn () => $this->topic?->name),
            'has_meeting' => $this->zoom_meeting_id !== null,
            // Whether the viewing staff member can go live as host right now.
            'can_start' => $request->user() !== null && $this->resource->hostableBy($request->user()),
            'recordings' => $this->whenLoaded('recordings', fn () => $this->recordings->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'status' => $r->status->value,
                // Staff-facing endpoint: expose the Zoom Cloud watch link + passcode.
                'watch_url' => $r->play_url,
                'passcode' => $r->passcode,
            ])->all()),
        ];
    }
}
