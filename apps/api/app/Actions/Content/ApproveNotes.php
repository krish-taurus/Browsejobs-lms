<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Actions\Tutor\IngestKnowledgeDocument;
use App\Enums\NoteStatus;
use App\Models\KnowledgeDocument;
use App\Models\LessonNote;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Trainer approval of AI notes (PRD §6.10 / §7 human checkpoint). Publishes the notes to
 * students and — closing the P3.3 deferral — ingests the transcript into the AI Tutor
 * knowledge base so the tutor can cite class content. Only an approved transcript is
 * citable (the KB doc is is_active ⇔ the note is Approved). Idempotent; audited.
 */
final readonly class ApproveNotes
{
    public function __construct(
        private AuditLogger $audit,
        private IngestKnowledgeDocument $ingest,
    ) {}

    public function handle(LessonNote $note, ?User $actor = null): LessonNote
    {
        return app(TenantContext::class)->run($note->tenant, function () use ($note, $actor): LessonNote {
            if ($note->status === NoteStatus::Approved) {
                return $note;
            }

            if (! $note->hasNotes()) {
                throw ValidationException::withMessages(['notes' => 'Generate or write notes before approving.']);
            }

            $note->update([
                'status' => NoteStatus::Approved->value,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ]);

            $this->audit->log(
                action: 'notes.approved',
                target: $note,
                metadata: ['lesson_id' => $note->lesson_id],
                actor: $actor,
            );

            $courseId = $note->lesson?->topic?->module?->course_id;

            $document = KnowledgeDocument::query()->updateOrCreate(
                ['source_type' => 'transcript', 'source_id' => $note->id],
                [
                    'tenant_id' => $note->tenant_id,
                    'title' => 'Transcript: '.($note->lesson?->title ?? 'Lesson'),
                    'body' => $note->transcript,
                    'course_id' => $courseId,
                    'lesson_id' => $note->lesson_id,
                    'is_active' => true,
                ],
            );

            $this->ingest->handle($document);
            $note->update(['knowledge_document_id' => $document->id]);

            return $note->refresh();
        });
    }
}
