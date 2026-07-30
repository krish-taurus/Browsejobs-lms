<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmployerAutomationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployerAutomationRule */
final class EmployerAutomationRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->employer_job_id,
            'trigger' => $this->trigger,
            'round' => $this->round,
            'min_score' => $this->min_score,
            'action' => $this->action,
            'target_stage' => $this->target_stage,
            'enabled' => $this->enabled,
            'runs_count' => $this->whenCounted('runs'),
            'would_match' => $this->when(isset($this->would_match), fn () => $this->would_match),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
