<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A support policy document (PRD §6.13). `chunks_count` is exposed because zero chunks
 * means the document is written but unreachable — the one failure a policy author would
 * otherwise never see.
 *
 * @mixin KnowledgeDocument
 */
final class SupportDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'body' => $this->body,
            'is_active' => (bool) $this->is_active,
            'chunks_count' => $this->whenCounted('chunks'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
