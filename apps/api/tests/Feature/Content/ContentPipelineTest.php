<?php

declare(strict_types=1);

use App\Actions\Content\ApproveNotes;
use App\Actions\Content\GenerateNotes;
use App\Actions\Content\SaveTranscript;
use App\Actions\Tutor\RebuildTutorIndex;
use App\Enums\NoteSource;
use App\Enums\NoteStatus;
use App\Jobs\GenerateNotesJob;
use App\Models\AiEvent;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Content\TranscriptCleaner;
use App\Support\Tutor\KnowledgeRetriever;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->staff = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
});

function notesLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'notes', 'title' => 'Recursion class']);
}

/* ---- Cleaner (unit-style) ---- */

it('strips WebVTT cue timings and ids from a transcript', function () {
    $vtt = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nRecursion needs a base case.\n\n2\n00:00:04.000 --> 00:00:08.000\nOtherwise it never stops.";

    $clean = app(TranscriptCleaner::class)->clean($vtt);

    expect($clean)->not->toContain('-->')
        ->and($clean)->not->toContain('WEBVTT')
        ->and($clean)->toContain('base case');
});

/* ---- Save + generate ---- */

it('saves a cleaned transcript and queues notes generation', function () {
    Bus::fake();
    $lesson = notesLesson($this->tenant);

    $note = app(SaveTranscript::class)->handle($lesson, "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHello class.", NoteSource::Upload, $this->staff);

    expect($note->transcript)->not->toContain('-->')
        ->and($note->source)->toBe(NoteSource::Upload)
        ->and($note->status)->toBe(NoteStatus::Draft);
    Bus::assertDispatched(GenerateNotesJob::class, fn ($j) => $j->lessonNoteId === $note->id);
});

it('generates notes billed to staff, and stays draft', function () {
    $lesson = notesLesson($this->tenant);
    $note = LessonNote::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);
    $this->fake->reply = "## Key concepts\n\n- Recursion needs a base case.";

    expect(app(GenerateNotes::class)->handle($note, $this->staff))->toBeTrue();

    expect($note->fresh()->notes)->toContain('base case')
        ->and($note->fresh()->status)->toBe(NoteStatus::Draft)
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $this->staff->id)->where('purpose', 'content')->exists())->toBeTrue();
});

it('leaves notes null when the budget is exceeded, without throwing', function () {
    config(['ai.daily_token_budget' => 1]);
    $lesson = notesLesson($this->tenant);
    $note = LessonNote::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);
    AiEvent::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->staff->id, 'purpose' => 'content', 'model' => 'x',
        'prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10, 'cost_micros' => 0, 'latency_ms' => 1, 'status' => 'ok',
    ]);

    expect(app(GenerateNotes::class)->handle($note, $this->staff))->toBeFalse()
        ->and($note->fresh()->notes)->toBeNull();
});

/* ---- Approve → KB feed (closes P3.3 deferral) ---- */

it('approving notes ingests the transcript into the tutor KB and makes it retrievable', function () {
    $lesson = notesLesson($this->tenant);
    $note = LessonNote::factory()->for($this->tenant)->withNotes()->create([
        'lesson_id' => $lesson->id,
        'transcript' => 'In this class we covered recursion and the importance of a base case that stops the calls.',
    ]);

    app(ApproveNotes::class)->handle($note, $this->staff);

    $note->refresh();
    expect($note->status)->toBe(NoteStatus::Approved)
        ->and($note->knowledge_document_id)->not->toBeNull()
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'notes.approved')->count())->toBe(1);

    $doc = KnowledgeDocument::withoutGlobalScopes()->find($note->knowledge_document_id);
    expect($doc->source_type)->toBe('transcript')->and($doc->is_active)->toBeTrue()
        ->and(KnowledgeChunk::withoutGlobalScopes()->where('document_id', $doc->id)->count())->toBeGreaterThan(0);

    // The tutor can now retrieve the class transcript.
    $hits = withinTenant($this->tenant, function () {
        $r = app(KnowledgeRetriever::class);

        return $r->retrieve($r->tokenize('base case recursion'), 5);
    });
    expect($hits)->not->toBeEmpty();
});

it('re-approving is idempotent and editing retracts the transcript from the KB', function () {
    $lesson = notesLesson($this->tenant);
    $note = LessonNote::factory()->for($this->tenant)->withNotes()->create(['lesson_id' => $lesson->id, 'transcript' => 'Recursion base case content.']);

    app(ApproveNotes::class)->handle($note, $this->staff);
    app(ApproveNotes::class)->handle($note->fresh(), $this->staff);
    expect(KnowledgeDocument::withoutGlobalScopes()->where('source_type', 'transcript')->count())->toBe(1);

    // A new transcript resets approval + deactivates the KB doc.
    app(SaveTranscript::class)->handle($lesson->fresh(), 'A completely different lesson about loops.', NoteSource::Paste, $this->staff);
    $hits = withinTenant($this->tenant, function () {
        $r = app(KnowledgeRetriever::class);

        return $r->retrieve($r->tokenize('recursion base case'), 5);
    });
    expect($hits)->toBeEmpty(); // the old transcript is no longer citable
});

it('refuses to approve notes that are empty', function () {
    $lesson = notesLesson($this->tenant);
    $note = LessonNote::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id, 'notes' => null]);

    expect(fn () => app(ApproveNotes::class)->handle($note, $this->staff))
        ->toThrow(ValidationException::class);
});

/* ---- Reindex only includes approved transcripts ---- */

it('reindex includes approved transcripts and excludes drafts', function () {
    $approved = notesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->approved()->create(['lesson_id' => $approved->id, 'transcript' => 'Approved class about dictionaries in Python.']);
    $draft = notesLesson($this->tenant);
    LessonNote::factory()->for($this->tenant)->create(['lesson_id' => $draft->id, 'transcript' => 'Draft class about sets — should not be citable.']);

    app(RebuildTutorIndex::class)->handle($this->tenant);

    expect(KnowledgeDocument::withoutGlobalScopes()->where('source_type', 'transcript')->count())->toBe(1);
});
