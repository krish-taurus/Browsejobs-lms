<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/trainer view of a quiz + its questions (PRD §6.5).
 *
 * @mixin Quiz
 */
final class QuizResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'time_limit_sec' => $this->time_limit_sec,
            'pass_pct' => $this->pass_pct,
            'shuffle' => $this->shuffle,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'dispatchable' => $this->isDispatchable(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'questions' => QuizQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
