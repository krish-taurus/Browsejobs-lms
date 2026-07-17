# ADR 0022 — Content AI: transcript → study notes + AI Tutor KB feed (P3.5b)

- **Status:** Accepted
- **Date:** 2026-07-17
- **PRD:** §6.10 (Content AI); closes the P3.3 deferral ("transcripts fold into the same KB store")
- **Supersedes / relates to:** ADR 0017 (AI Tutor RAG), ADR 0018 (assessment MCQ automation — companion + approve pattern), 0021 (prose fail-safe)

## Context

A trainer needs class content turned into two things: **study notes** for students and
**citable knowledge** for the AI Tutor. P3.3 shipped the Tutor KB (course/program/lab sources)
but deferred folding class transcripts into it. P3.5b delivers the Content-AI slice: attach a
transcript to a `notes` lesson → AI drafts study notes (trainer-approved) → the transcript feeds
the same KB store so the tutor can cite what was said in class.

**Founder-confirmed scope (this session):**

- **Manual transcript only** — paste or `.txt`/`.vtt`/`.md` upload. **No audio-to-text (ASR):**
  the AI gateway is Anthropic text-only. An optional recording link is nullable. Auto-ASR is
  deferred behind the same `SaveTranscript` seam.
- **Notes + KB feed only.** Flashcards and transcript-driven draft quizzes are deferred (they
  reuse the P3.4 quiz machinery later).

## Decision

1. **One `lesson_notes` companion table** (`unique(lesson_id)`, mirroring `quizzes`/`coding_labs`/
   `assignments`): `recording_id` (nullable FK), `transcript` (longText, cleaned), `notes`
   (longText nullable, AI-generated), `source` (`paste`|`upload`), `status` (`draft`|`approved`),
   `generated_by`/`approved_by`/`approved_at`, `knowledge_document_id` (nullable — the KB doc it
   feeds). New enums `NoteStatus`, `NoteSource`; new `AiPurpose::Content`. `BelongsToTenant` +
   `tenant_id` index/FK. New migration `2026_07_16_200009` (never edits a merged one).

2. **Manual transcript → cleaned text.** `Support/Content/TranscriptCleaner` normalises newlines
   and, for WebVTT, strips the `WEBVTT` header, `NOTE`/`STYLE` blocks, `-->` cue timings, numeric
   cue ids and inline `<...>` tags; collapses blank runs; strips control chars; caps to
   `config('content.max_transcript_chars')`. `SaveTranscript` upserts the row with the cleaned
   text, **resets approval to draft**, **deactivates any prior KB doc** (`is_active=false`), and
   dispatches `GenerateNotesJob`. `.vtt`/`.txt`/`.md` uploads are read inline (`$file->get()`) —
   small text, not persisted to disk.

3. **AI notes are prose, staff-billed, and fail-safe.** `GenerateNotes` calls the gateway
   (`AiPurpose::Content`, versioned prompt `resources/prompts/notes.v1.md`) with the transcript +
   lesson/module/course context; the generating **staff** actor is billed (like quiz-gen). On
   `AiBudgetExceeded` or empty output it **leaves `notes` null** for manual authoring — it never
   throws out of the queued job and never invents prose (mirrors the P3.5a `ReportNarrator`
   fail-safe). Notes stay **draft**: a trainer must approve.

4. **`ApproveNotes` closes the P3.3 deferral.** It mirrors `ApproveQuiz` (idempotent; requires
   non-empty notes else `ValidationException`; audit `notes.approved`), then ingests the
   transcript into the KB: `KnowledgeDocument::updateOrCreate(['source_type'=>'transcript',
   'source_id'=>$note->id], ['body'=>$transcript, 'lesson_id', 'course_id', 'is_active'=>true, …])`
   + `IngestKnowledgeDocument::handle($doc)` (chunks it), and stores `knowledge_document_id`.
   `source_type` is a free string, so `'transcript'` needs no schema change.

5. **Invariant — a transcript is citable ⇔ its note is Approved.** Enforced in four places:
   `ApproveNotes` sets `is_active=true`; `SaveTranscript`/controller `revertToDraft` set
   `is_active=false` on edit/un-approve; `RebuildTutorIndex::sources()` has a transcript branch
   that enumerates **approved notes only**; and the retriever's `is_active` filter is the backstop.

6. **Students see notes, never the transcript.** `me/lessons/{lesson}/notes` returns approved
   notes only, via a lean payload with **no `transcript` field**. `CitationResolver` routes a
   transcript citation to `/notes/{lesson}` (vs `/labs/{lesson}` for lab docs). No new permission:
   build/approve run under `can:manage-curriculum`; the student surface under `me/*`.

## Consequences

- **Positive:** Class content becomes both study notes and tutor-citable knowledge through one
  approve action; the human-approval gate (PRD §7) keeps AI notes off the student surface until a
  trainer signs off; the ASR seam is isolated to `SaveTranscript`; the KB store is unified.
- **Trade-offs:** Notes are only as good as the manually supplied transcript (no ASR yet). One
  transcript per lesson (companion `unique(lesson_id)`), consistent with quizzes/labs/assignments.
- **Deferred (owner-visible, not gaps):** flashcards + transcript-driven draft quiz; auto
  audio→text (external ASR behind `SaveTranscript`); AI syllabus generator + PDF (P3.5c);
  support-desk AI (P3.5d).

## Verification

`php artisan test` (415 passing, incl. `Feature/Content/ContentPipelineTest` +
`LessonNoteHttpTest`): VTT cue stripping; save cleans + dispatches; notes billed to staff, stay
draft; budget-exceeded → notes null, no throw; **approve ingests a `source_type=transcript`
KnowledgeDocument + chunks and the retriever finds it**; re-approve idempotent + edit retracts it
from the KB; approve-empty rejected; reindex includes approved, excludes drafts; student surface
returns approved notes only (draft → 404) and **never leaks the transcript**; admin gate + both
cross-tenant denials. Pint clean; web typecheck/lint/build green. Fresh `migrate --seed`
(`ContentSeeder`) → an approved notes lesson whose transcript is active in the KB with chunks and
retrievable (verified via tinker). Playwright: `admin-content-notes.spec.ts`.
