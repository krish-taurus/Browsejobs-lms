<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Content\ApproveNotes;
use App\Actions\Content\RenderNotesPdf;
use App\Actions\Content\SaveTranscript;
use App\Enums\LessonType;
use App\Enums\NoteSource;
use App\Enums\NoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\SaveTranscriptRequest;
use App\Http\Requests\Content\UpdateNotesRequest;
use App\Http\Requests\Content\UploadTranscriptRequest;
use App\Http\Resources\LessonNoteResource;
use App\Jobs\GenerateNotesJob;
use App\Models\KnowledgeDocument;
use App\Models\Lesson;
use App\Models\LessonNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin/trainer class-notes builder (PRD §6.10). Gated by `can:manage-curriculum`.
 * Save a transcript → AI drafts notes → trainer edits/approves; approval publishes the
 * notes to students and feeds the transcript to the AI Tutor.
 */
final class LessonNoteController extends Controller
{
    public function index(): JsonResponse
    {
        $lessons = Lesson::query()
            ->where('type', LessonType::Notes->value)
            ->with(['topic:id,name,module_id', 'topic.module:id,name,course_id', 'topic.module.course:id,name'])
            ->orderBy('id')
            ->get();

        $notes = LessonNote::query()->whereIn('lesson_id', $lessons->pluck('id'))->get()->keyBy('lesson_id');

        return response()->json(['data' => $lessons->map(function (Lesson $lesson) use ($notes): array {
            $note = $notes->get($lesson->id);

            return [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'course' => $lesson->topic?->module?->course?->name,
                'module' => $lesson->topic?->module?->name,
                'has_transcript' => $note !== null,
                'has_notes' => (bool) $note?->hasNotes(),
                'status' => $note?->status->value,
            ];
        })->all()]);
    }

    public function show(Lesson $lesson): JsonResponse
    {
        $note = LessonNote::query()->where('lesson_id', $lesson->id)->first();

        return response()->json(['data' => $note === null ? null : new LessonNoteResource($note)]);
    }

    public function upsert(SaveTranscriptRequest $request, Lesson $lesson, SaveTranscript $save): JsonResponse
    {
        abort_unless($lesson->type === LessonType::Notes, 422, 'Lesson is not a notes lesson.');

        $note = $save->handle(
            $lesson,
            $request->string('transcript')->toString(),
            NoteSource::Paste,
            $request->user(),
            $request->integer('recording_id') ?: null,
        );

        return (new LessonNoteResource($note))->response()->setStatusCode(202);
    }

    public function upload(UploadTranscriptRequest $request, Lesson $lesson, SaveTranscript $save): JsonResponse
    {
        abort_unless($lesson->type === LessonType::Notes, 422, 'Lesson is not a notes lesson.');

        $text = (string) $request->file('file')->get();

        $note = $save->handle(
            $lesson,
            $text,
            NoteSource::Upload,
            $request->user(),
            $request->integer('recording_id') ?: null,
        );

        return (new LessonNoteResource($note))->response()->setStatusCode(202);
    }

    public function generate(Request $request, Lesson $lesson): JsonResponse
    {
        $note = LessonNote::query()->where('lesson_id', $lesson->id)->firstOrFail();

        GenerateNotesJob::dispatch($note->id, $request->user()->id);

        return response()->json(['data' => ['status' => 'generating', 'lesson_id' => $lesson->id]], 202);
    }

    public function updateNotes(UpdateNotesRequest $request, Lesson $lesson): JsonResponse
    {
        $note = LessonNote::query()->where('lesson_id', $lesson->id)->firstOrFail();
        $note->update(['notes' => $request->string('notes')->toString()]);
        $this->revertToDraft($note);

        return (new LessonNoteResource($note->refresh()))->response();
    }

    public function approve(Request $request, LessonNote $note, ApproveNotes $approve): JsonResponse
    {
        $approve->handle($note, $request->user());

        return (new LessonNoteResource($note->refresh()))->response();
    }

    /** Render the notes markdown into a branded downloadable PDF. */
    public function generatePdf(Lesson $lesson, RenderNotesPdf $render): JsonResponse
    {
        $note = LessonNote::query()->where('lesson_id', $lesson->id)->firstOrFail();

        abort_if(blank($note->notes), 422, 'Add or generate notes before making a PDF.');

        $render->handle($note);

        return response()->json(['data' => ['url' => $this->pdfUrl($note->refresh())]]);
    }

    /** Attach a manually-prepared PDF instead of generating one. */
    public function uploadPdf(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:20480']]);

        $note = LessonNote::query()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            ['tenant_id' => $lesson->tenant_id, 'transcript' => '', 'source' => NoteSource::Upload->value, 'status' => NoteStatus::Draft->value],
        );

        $path = "notes/{$lesson->tenant_id}/lesson-{$lesson->id}.pdf";
        Storage::disk(RenderNotesPdf::DISK)->put($path, $request->file('file')->getContent(), 'private');
        $note->update(['pdf_path' => $path, 'pdf_uploaded' => true]);

        return response()->json(['data' => ['url' => $this->pdfUrl($note->refresh())]]);
    }

    /** A short-lived download URL for the note's PDF (admin preview). */
    public function downloadPdf(Lesson $lesson): JsonResponse
    {
        $note = LessonNote::query()->where('lesson_id', $lesson->id)->firstOrFail();
        abort_if($note->pdf_path === null, 404, 'No PDF yet.');

        return response()->json(['data' => ['url' => $this->pdfUrl($note)]]);
    }

    private function pdfUrl(LessonNote $note): ?string
    {
        if ($note->pdf_path === null) {
            return null;
        }

        return Storage::disk(RenderNotesPdf::DISK)->temporaryUrl($note->pdf_path, now()->addMinutes(15));
    }

    /** Editing an approved note un-approves it and retracts its transcript from the tutor KB. */
    private function revertToDraft(LessonNote $note): void
    {
        if ($note->status === NoteStatus::Approved) {
            if ($note->knowledge_document_id !== null) {
                KnowledgeDocument::query()->whereKey($note->knowledge_document_id)->update(['is_active' => false]);
            }
            $note->update(['status' => NoteStatus::Draft->value, 'approved_by' => null, 'approved_at' => null]);
        }
    }
}
