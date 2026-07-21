<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NoteStatus;
use App\Models\LessonNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/trainer view of a lesson's transcript + AI notes (PRD §6.10). Includes the raw
 * transcript — the student resource never does.
 *
 * @mixin LessonNote
 */
final class LessonNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'recording_id' => $this->recording_id,
            'transcript' => $this->transcript,
            'notes' => $this->notes,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'has_notes' => $this->hasNotes(),
            'has_pdf' => $this->pdf_path !== null,
            'pdf_uploaded' => (bool) $this->pdf_uploaded,
            'is_citable' => $this->status === NoteStatus::Approved && $this->knowledge_document_id !== null,
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
