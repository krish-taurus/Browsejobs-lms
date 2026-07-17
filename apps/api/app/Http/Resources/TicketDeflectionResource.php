<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TicketDeflection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An offered AI first-response (PRD §6.13). `citations` is attached by the controller
 * via `CitationResolver` — derived from the chunks that were retrieved, never parsed
 * out of the model's answer.
 *
 * @mixin TicketDeflection
 */
final class TicketDeflectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'answer' => $this->answer,
            'confidence' => $this->confidence->value,
            'citations' => $this->getAttribute('citations') ?? [],
            'outcome' => $this->outcome->value,
        ];
    }
}
