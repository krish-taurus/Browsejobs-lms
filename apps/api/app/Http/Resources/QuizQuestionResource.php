<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/trainer view of an MCQ (PRD §6.5) — includes the correct answer. The
 * student-facing MCQ payload is built separately and never exposes `correct_index`.
 *
 * @mixin QuizQuestion
 */
final class QuizQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'options' => $this->options,
            'correct_index' => $this->correct_index,
            'explanation' => $this->explanation,
            'position' => $this->position,
        ];
    }
}
