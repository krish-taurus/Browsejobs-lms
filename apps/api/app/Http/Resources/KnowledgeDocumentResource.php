<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A knowledge-base document for the admin authoring surface (PRD §6.4).
 *
 * @mixin KnowledgeDocument
 */
final class KnowledgeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'source_type' => $this->source_type,
            'body' => $this->body,
            'course_id' => $this->course_id,
            'lesson_id' => $this->lesson_id,
            'is_active' => (bool) $this->is_active,
            'chunk_count' => $this->whenCounted('chunks'),
            'editable' => $this->source_type === 'manual',
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
