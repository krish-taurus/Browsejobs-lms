# ADR 0018 — P3.4 Assessment automation (MCQ spine): design decisions

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD v1.4 §6.5 + §7 + CLAUDE.md + BUILD-PLAYBOOK P3.4. Completes the Phase-3 gate.

## Context

P3.4 turns a completed module into an auto-dispatched, timed, integrity-checked MCQ
whose score feeds the P3.2 mastery map — closing the Phase-3 gate: "finish a module →
MCQ link arrives on WhatsApp → score updates the Coach Panel." Everything
assessment-side was greenfield, but the dispatch chain (magic links, the Messenger
hub, in-app notifications, `ModuleCompleted` auto-discovered listeners, the
delayed-armed-job reminder pattern, the Testimonial approve flow, the queued
render-job template) was reused wholesale.

**Founder-confirmed scope (this session):** the **MCQ spine only** — quiz builder + AI
quiz-gen (trainer-approved) + `ModuleCompleted` dispatch + timed MCQ page + attempts →
mastery + 48h/96h reminders. **AI assignment grading (P3.4b) and certificates (P3.4c)
are deferred.** Quizzes are AI-generated **and** manually authorable, always
trainer-approved.

## Decisions

1. **Quizzes are a companion table to `quiz` lessons** (mirrors `CodingLab`):
   `quizzes` (1/lesson, `unique(lesson_id)`) → `quiz_questions` → `quiz_attempts`
   (`unique(quiz_id,user_id)`). Enums `QuizStatus` (draft|approved), `QuizAttemptStatus`
   (pending|in_progress|submitted|expired), `QuizSource` (ai|manual).
2. **AI generation is a queued, trainer-approved draft** — the first queued AI-job in
   the codebase (Tutor was sync). `GenerateQuizDraft` (billed to the triggering **staff**
   user, never a student; catches `AiBudgetExceeded` → leaves a draft) → `GenerateQuiz`
   calls the gateway with `quiz_gen.v1`, robustly extracts the JSON array (tolerates
   prose/fences, validates each item, drops malformed ones), and replaces the draft
   questions. Only an **approved** quiz dispatches; editing an approved quiz reverts it
   to draft (so a stale quiz never ships). `ApproveQuiz` mirrors `ApproveTestimonial`
   (idempotent, audited).
3. **Dispatch on `ModuleCompleted`** via the queued `DispatchModuleQuiz` listener:
   find the module's `quiz` lesson + a dispatchable `Quiz` → `QuizAttempt::firstOrCreate`
   (the unique index makes re-fired events a no-op — no double message) →
   `Messenger::send('mcq_dispatch', magic 'mcq.attempt' → /mcq/{attempt})` → arm the
   ladder. **`deadline_at` is set when the student first opens the page**, not at
   dispatch, so the timer is fair regardless of when they click the link.
4. **Server-authoritative timer + advisory integrity.** `show` sets `in_progress` +
   `deadline_at = now + time_limit_sec` on first open and returns questions **shuffled**
   (deterministic per-attempt hash) with `correct_index` **stripped**. `SubmitQuizAttempt`
   re-checks `now > deadline_at` (with a 5s grace) → late = `expired`, grades
   server-side, and records `integrity {tab_blurs, paste_count, duration_sec}` for
   **trainer review only** (never auto-punishes, per PRD). Correct answers + explanations
   are revealed only in the submit response.
5. **Quiz-weighted mastery blend.** `config('scoring.mastery.quiz_weight')` (0.4);
   `ScoreCalculator::mastery()` blends the module's latest **submitted** attempt:
   `pct = round(topicPct*0.6 + quizPct*0.4)` **only when a submitted attempt exists** —
   so the existing P3.2 fixtures (no attempts) are byte-for-byte unchanged. A dedicated
   determinism test guards this.
6. **48h reminder / 96h counselor flag = delayed armed jobs on the attempt row.**
   `CheckQuizCompletion(attemptId, phase)` armed with `->delay(48h/96h)` at dispatch,
   each phase idempotent + sync-driver self-guarded via the attempt's
   `reminded_at`/`flagged_at` (no separate dispatch table). The 96h flag creates a
   `CrmTask` (`SOURCE_QUIZ`) against the correlated lead's assignee (falls through when
   there's no CRM owner — the timestamp still records the miss). Hourly safety-net sweep
   `quizzes:check-dispatch` backs the delayed jobs.
7. **No new permission.** Quiz build/approve under `can:manage-curriculum` (trainers +
   admins hold it); the student MCQ under `me/*` (tenant-wrapped) with `throttle:30,1`
   on submit.

## Consequences

- Verified on a seeded scratch DB + 19 Pest cases: AI-gen drops malformed items and
  bills the staff user; approval is audited + idempotent; `ModuleCompleted` dispatches
  one attempt + a faked WhatsApp magic link (no double-dispatch); the 48h reminder +
  96h `CrmTask` fire once each under frozen time; `me/mcq` starts the timer, hides
  answers, and denies cross-tenant/guest access; submit grades + records integrity +
  marks a late submit `expired`; a submitted quiz blends to 70% mastery while no-attempt
  stays at the pure topic value. `QuizSeeder` ships an approved quiz + a submitted
  sample attempt.
- New: `quizzes`/`quiz_questions`/`quiz_attempts` (+ models/factories/enums);
  `quiz_gen.v1` prompt; `GenerateQuiz`/`ApproveQuiz`/`SubmitQuizAttempt`/`RunQuizDispatchSweep`
  actions; `GenerateQuizDraft`/`CheckQuizCompletion` jobs; `DispatchModuleQuiz` listener;
  `quizzes:check-dispatch` command; admin `QuizController` + student `MyQuizController`
  (+ Requests/Resources); `mcq_dispatch`/`mcq_reminder` templates; `scoring.mastery.quiz_weight`;
  `CrmTask::SOURCE_QUIZ`; frontend `<Countdown>`, the `/mcq/[attempt]` runner, and the
  admin quizzes manager.
- **Deferred (owner-visible, not gaps):** **P3.4b** AI assignment grading (assignment
  companion table, submissions, rubric, queued AI grade, trainer approve/edit,
  AI-likelihood signal); **P3.4c** auto-issued branded certificates (HTML via the
  ReceiptRenderer seam) + QR + public verify page (adds a QR composer dep); also quiz
  analytics, question banks, and retake policy. Standing ops note: production cron now
  also runs `quizzes:check-dispatch` (alongside `fees:run-ladder`, `conversions:run-nudges`,
  `support:check-sla`, `scores:recompute`, `tutor:reindex`). Next after P3.4b/c:
  **P3.5 — Content AI + reports**.
