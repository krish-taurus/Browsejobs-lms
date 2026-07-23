# ADR 0049 — Day-by-day syllabus builder

Date: 2026-07-23 · Status: accepted

## Context

The founder wants trainers/mentors/admins to build a module's syllabus one
teaching day at a time, with two entry modes per day — type it manually, or
give the AI keywords and have it draft the day. Each day then needs (a) a
beginner-language notes document ("explained like to an absolute fresher",
short sentences, tiny examples) downloadable as a PDF, and (b) an attached
assignment — AI-generated multiple choice or a mentor-uploaded PDF paper.

## Decision

**A day is a Topic.** No new hierarchy level: `topics` gains `day_number`
(unique per module in the day-builder flow), `keywords` (json), and `summary`.
The existing Module → Topic → Lesson tree, CSV import, clone, and the
course-level AI syllabus (P3.5c) are untouched.

**AI day plans are drafts.** `GenerateDayPlan` (prompt `day_plan.v1`, new
`AiPurpose::DayPlan`) turns keywords into `{name, summary, subtopics,
keywords}` and persists nothing — the trainer reviews, edits, and saves
(the same draft-first rule as assignment briefs).

**Beginner notes ride the LessonNote pipeline.** `GenerateSimpleNotes`
(prompt `simple_notes.v1`, ELI5 voice) auto-creates the topic's `notes`
lesson and writes a draft `LessonNote` — so the existing approve step
(PRD §7), dompdf PDF render, manual PDF upload, and student portal delivery
all apply unchanged. The topic outline stands in for the transcript.

**Assignments are day-focused quizzes with a printable paper.**
`GenerateTopicAssignment` ensures a `quiz` lesson + quiz shell and drafts
MCQs through the existing `GenerateQuiz` (whose prompt vars now focus on a
single day when the topic carries keywords). New `RenderQuizPdf` renders
questions + a trainer-only answer key page to a private s3 PDF
(`quizzes.pdf_path`); mentors can instead upload their own paper
(`pdf_uploaded`), mirroring lesson-note PDFs.

**Images in notes** are out of scope for v1: the model writes analogies and
walked-through examples, not pictures. Diagrams can be added to the blade
template later without touching the pipeline.

## Consequences

- New admin surface: `/admin/curriculum/{course}/days/{module}` (Day builder),
  linked from the curriculum tree; routes under `can:manage-curriculum`, AI
  endpoints behind `throttle:ai`.
- All AI output stays draft until a human approves — nothing AI-written
  reaches students directly.
- Rendered papers include the answer key on a separate trainer-only page;
  hand out all but the last page.
