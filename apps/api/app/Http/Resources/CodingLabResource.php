<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CodingLab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The student view of a coding lab (PRD §6.5) — hidden test expected-outputs are
 * never exposed (only the count).
 *
 * @mixin CodingLab
 */
final class CodingLabResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'lesson_id' => $this->lesson_id,
            'title' => $this->whenLoaded('lesson', fn () => $this->lesson?->title),
            'language' => $this->language->value,
            'monaco' => $this->language->monaco(),
            'instructions' => $this->instructions,
            'starter_code' => $this->starter_code,
            'time_limit_ms' => $this->time_limit_ms,
            'test_count' => count($this->test_cases ?? []),
        ];
    }
}
