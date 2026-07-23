# ADR 0050 — Chapters, notes-first assignments, candidate performance

Date: 2026-07-23 · Status: accepted · Extends: ADR 0049

## Context

Founder feedback on the day builder: the structure should read as **Module →
Chapters** ("Chapter 1: Python data types"), notes must come before
assignments, chapters can carry several assignments (each with a unique id
sent to candidates), and every module completion should end with an automatic
mock invite plus a performance report — tracked batch-wise in an admin
dashboard with weighted RAG scoring.

## Decisions

1. **Chapters are the same Topic rows** (ADR 0049's `day_number` doubles as
   the chapter number) — pure relabel, no schema change.
2. **Notes-first, notes-sourced assignments.** Assignment generation 422s
   (`notes_required`) until the chapter has notes; questions are generated
   FROM the notes (`quiz_from_notes.v1`) so they only test what students were
   given. Each generation creates a NEW numbered quiz lesson
   ("… — Assignment N"); the quiz id (shown as `A-00042`) is the unique code.
   The ELI5 notes prompt now adds a **Where you'll use this** real-world
   section per idea.
3. **Module-completion automation reuses the existing event.** On
   `ModuleCompleted` the platform already dispatches the module quiz and
   opens the mock quota with an invite; the new queued `SendModuleReport`
   listener adds the founder's report — WhatsApp/email (`module_report`
   template) + in-app card with the weighted score.
4. **One score, everywhere: `CandidatePerformance`.** Attendance avg ×0.5 +
   completed-mock avg ×0.3 + submitted-assignment share ×0.2. Bands:
   green ≥ 80, amber 60–79.9, red < 60. Deterministic, computed live from the
   same tables students see — no cached divergence. Used by both the module
   report and the new **Admin → Candidate performance** dashboard
   (batch/name filters, rank, click-through to component breakdown, module
   mastery, recent mocks).
5. Mock blueprints remain **course-level** (per P4.x design); module-specific
   question banks are future work, as is wiring this score into the
   leaderboard (founder: "discuss later").
