<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\NoteSource;
use App\Enums\NoteStatus;
use App\Jobs\GenerateNotesJob;
use App\Models\KnowledgeDocument;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\User;
use App\Support\Content\TranscriptCleaner;
use App\Support\Tenancy\TenantContext;

/**
 * Stores a lesson's transcript (PRD §6.10) and queues notes generation. Cleaning the
 * text keeps KB chunks coherent. A new/changed transcript resets approval and
 * deactivates any prior KB document — a draft transcript must not stay citable.
 */
final readonly class SaveTranscript
{
    public function __construct(private TranscriptCleaner $cleaner) {}

    public function handle(Lesson $lesson, string $rawTranscript, NoteSource $source, User $actor, ?int $recordingId = null): LessonNote
    {
        return app(TenantContext::class)->run($lesson->tenant, function () use ($lesson, $rawTranscript, $source, $actor, $recordingId): LessonNote {
            $text = $this->cleaner->clean($rawTranscript);

            $note = LessonNote::query()->firstOrNew(['lesson_id' => $lesson->id]);
            $note->fill([
                'tenant_id' => $lesson->tenant_id,
                'transcript' => $text,
                'source' => $source->value,
                'recording_id' => $recordingId,
                // A changed transcript is no longer approved.
                'status' => NoteStatus::Draft->value,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $note->save();

            // Retract the transcript from the tutor KB until it's re-approved.
            if ($note->knowledge_document_id !== null) {
                KnowledgeDocument::query()->whereKey($note->knowledge_document_id)->update(['is_active' => false]);
            }

            GenerateNotesJob::dispatch($note->id, $actor->id);

            return $note;
        });
    }
}
