<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TicketRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketRoute
 */
final class TicketRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'label' => $this->label,
            'routing' => $this->routing,
            'support_team_id' => $this->support_team_id,
            'support_team' => $this->whenLoaded('supportTeam', fn () => $this->supportTeam?->name),
            'first_response_minutes' => $this->first_response_minutes,
            'resolution_minutes' => $this->resolution_minutes,
            'active' => $this->active,
        ];
    }
}
