<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NoteSource;
use App\Enums\NoteStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A class transcript + its AI study notes for a `notes` lesson (PRD §6.10). One per
 * lesson (mirrors Quiz/CodingLab). Approved notes are student-visible; the approved
 * transcript feeds the AI Tutor knowledge base.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $lesson_id
 * @property int|null $recording_id
 * @property string $transcript
 * @property string|null $notes
 * @property NoteSource $source
 * @property NoteStatus $status
 * @property int|null $generated_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $knowledge_document_id
 */
class LessonNote extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lesson_id', 'recording_id', 'transcript', 'notes', 'source', 'status',
        'generated_by', 'approved_by', 'approved_at', 'knowledge_document_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => NoteSource::class,
            'status' => NoteStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === NoteStatus::Approved;
    }

    public function hasNotes(): bool
    {
        return filled($this->notes);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Recording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function knowledgeDocument(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }
}
