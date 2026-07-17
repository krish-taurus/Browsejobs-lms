# ADR 0042 — Skill-tagged mocks & targeted batch dispatch

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.6 (mock interviewer), §6.5 (assignments)
- **Relates to:** P4.1 (mock interviewer), P3.4b (assignments), ADR 0036 (class scheduling)

## Context

Mocks were one blueprint per course — a student could only ever start "the" mock, and an
admin had no way to send a *specific* one. Assignments had no dispatch path at all
(reached only by deep link). The founder wanted to send a specific mock ("Python mock" vs
"SQL mock") and a specific assignment to a batch after a class.

## Decision

1. **A `skill` tag on `mock_blueprints`.** A course can now hold several active blueprints
   — a Python mock, a SQL mock — each with an optional skill label. Null keeps the legacy
   single-blueprint behaviour.

2. **Students start a specific blueprint.** `MockBlueprint::availableFor($student)` returns
   the active blueprints for their course(s); `StartMockInterview` takes an optional
   `blueprintId` and honours it **only if it's one of the student's available blueprints**
   (else it falls back to the default, so a stale or foreign id can never start someone
   else's mock). The `/mock` page shows a skill picker when there's more than one, and a
   dispatched link (`/mock?start=<id>`) preselects it. `activeFor` is unchanged, so every
   existing untargeted start path still works.

3. **Targeted dispatch to a batch**, gated `can:teach-classes` (the trainer who took the
   class sends its follow-up): `dispatch-mock` and `dispatch-assignment` notify every active
   student with an in-app notification linking straight to that mock or assignment, and the
   mock also reuses the existing `mock_nudge` WhatsApp/email template. `dispatch-options`
   lists only the mocks and assignments belonging to the batch's course, and the send
   endpoints re-check that scope — you can't push a mock from another course into a batch.

4. **Assignments got their first dispatch mechanism.** Unlike quizzes (auto-dispatched on
   `ModuleCompleted`), assignments are sent deliberately by a trainer; this is that action,
   built on a new `Lesson::assignment` relation. In-app only — no template dependency.

## Consequences

- **Positive:** "Send the Python mock" and "send today's assignment" to a batch are one
  click each; students pick which skill to practise. Multi-blueprint courses are now first-
  class.
- **Trade-offs:** Dispatch is one blueprint / one assignment per call (no multi-select), and
  targets a whole batch (not a sub-group of students) — enough for the class-follow-up use
  case, extendable later. The `mock_nudge` template is reused for the mock dispatch rather
  than a bespoke "your trainer sent you" copy; the in-app notification carries the specific
  wording.
- **Deferred:** dispatch to selected students; a bespoke dispatch message template; tying a
  dispatch to a specific `LiveSession` so it fires automatically when a class ends (needs the
  session→lesson link noted in ADR 0041).

## Verification

`php artisan test` — full suite **699 passing** (690 + 9), incl.
`Feature/Mocks/SkillMockDispatchTest`: create a skill-tagged blueprint; the student is
**offered their skill mocks** and **starts the specific one they picked**; **a specific mock
and a specific assignment dispatch to the batch** with the right notification URL; options
are **scoped to the batch's course**; a mock from another course is **refused (404)**;
dispatch **requires teach-classes**; and **cross-tenant 404**. Pint clean. `npm run
typecheck`, `lint`, `build` pass; fresh `migrate --seed` applies the `skill` column. The
admin mocks form takes a skill, the batch page has a "Send to this batch" panel (mock +
assignment), and the student `/mock` page shows a skill picker and honours `?start=`.
