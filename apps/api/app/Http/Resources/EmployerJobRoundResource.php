<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerJobRound;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployerJobRound */
final class EmployerJobRoundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'position' => $this->position,
            'name' => $this->name,
            'kind' => $this->kind,
            'focus_skills' => $this->focus_skills ?? [],
            'competency_weights' => $this->competency_weights ?? [],
            'format_mix' => $this->format_mix ?? [],
            'question_count' => $this->question_count,
            'notes' => $this->notes,
            'window_hours' => $this->window_hours,
            'dispatch' => $this->dispatch,
            'auto_min_score' => $this->auto_min_score,
            'enabled' => $this->enabled,
            // Whether the round can be removed outright or only switched off:
            // one that has already been sat is evidence, not configuration.
            'has_interviews' => $this->interviews()->exists(),
        ];
    }
}
