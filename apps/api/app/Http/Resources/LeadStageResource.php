<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LeadStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadStage
 */
final class LeadStageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'position' => $this->position,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
            'leads_count' => $this->whenCounted('leads'),
        ];
    }
}
