# ADR 0019 — P3.4b AI assignment grading: design decisions

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD v1.4 §6.5 + §7 + CLAUDE.md. The deferred half of P3.4 (assignment grading).

## Context

P3.4a shipped the MCQ spine. P3.4b adds the other half of PRD §6.5: "AI grades assignments
against a trainer rubric → trainer approves → student receives." A trainer authors an
assignment + rubric; a student submits (text + optional link + optional file); on submit an
AI grades it against the rubric (**queued**) into a **draft** grade (per-criterion scores +
feedback + a trainer-only AI-authorship-likelihood signal); the trainer edits/releases; only
then does the student see it. The domain is greenfield but mirrors the P3.4a quiz feature and
reuses the file-upload, approve/edit, and queued-AI-job patterns.

**Founder-confirmed scope (this session):** submission = text + optional link + optional file
upload; **include** the AI-likelihood signal (trainer-only, caveated); grading auto-runs on
submit; grade hidden until released; reuse `manage-curriculum`.

## Decisions

1. **Three companion tables.** `assignments` (one per `assignment`/`project` lesson,
   `unique(lesson_id)`, `rubric` json `[{criterion, max_points}]` — mirrors `coding_labs.test_cases`);
   `assignment_submissions` (one active per assignment+student, text + link + a single optional
   file stored as `file_path/file_name/file_mime/file_size`); `assignment_grades` (one per
   submission, draft→approved). Enums `AssignmentSubmissionStatus`, `GradeStatus`.
2. **Auto-grade on submit → draft.** `SubmitAssignment` upserts the submission (storing the file
   via `StoreSubmissionFile`, mirroring `StoreTicketAttachments`, `'s3'` disk), drops any prior
   draft grade, and dispatches `GradeSubmissionJob` (queued — the second real AI job after
   quiz-gen). `GradeSubmission` calls the gateway with `grading.v1`, robust-parses the JSON,
   **clamps each criterion score to its rubric max (0-floor)** and sums the overall score, then
   upserts a **draft** `assignment_grade`. Unparseable output → no grade (manual fallback).
3. **Grading billed to the batch trainer, not the student.** The billing user is the trainer of
   the student's occupying batch on the assignment's course (`RouteTicket::batchTrainer` logic),
   falling back to the student when there's no trainer. Keeps AI-grading cost on the staff side
   (like quiz-gen), off the student's interactive tutor budget. `AiBudgetExceeded` leaves the
   submission awaiting a manual grade (no crash-loop). `ai_event_id` is captured post-call by
   reading the latest `Grading` `AiEvent` for the billing user (no gateway signature change).
4. **Draft→approved release gate.** `ReleaseGrade` (mirrors `ApproveQuiz`): guard → status
   approved + graded_by + approved_at → submission Graded → `AuditLogger::log('grade.released')`
   → `Messenger grade_ready`. `UpdateGrade` edits feedback/criteria (re-clamp + re-sum,
   `grade.updated` audit) and may edit a released grade (stays released). A released grade
   short-circuits any late re-grade and locks resubmission.
5. **AI-likelihood is advisory + trainer-only.** The grading model returns a 0–100 estimate,
   stored on the grade and shown to trainers **with a caveat** ("low confidence, advisory only,
   never share with the student"). It is **structurally excluded** from every student-facing
   payload — the student `show`/`grades` endpoints build a hand-picked array that never includes
   it, in either draft or released state, and two tests assert the serialized student response
   never contains the string `ai_likelihood`.
6. **Gated visibility.** The student sees a grade only when `status=approved`; a draft grade
   serializes as `null` for the student. Trainers use a separate full payload (score + feedback +
   criteria + ai_likelihood + file signed-URL).
7. **No new permission.** Build/grade under `can:manage-curriculum` (trainers hold it); student
   under `me/*` (tenant-wrapped, `throttle:20,1` on submit).

## Consequences

- Verified on a seeded scratch DB + 13 Pest cases: grading clamps an over-max score to the
  rubric maximum, bills the trainer (student fallback proven), and drops unparseable output;
  release audits + notifies + flips the student's view; the student payload hides a draft grade
  and never contains `ai_likelihood` in either state; a file submission stores to a faked s3;
  edit re-clamps + audits; the queue is `manage-curriculum`-gated + cross-tenant scoped.
  `AssignmentSeeder` ships an assignment + rubric + a released 82/100 grade.
- New: `assignments`/`assignment_submissions`/`assignment_grades` (+ models/factories/enums);
  `grading.v1` prompt; `GradeSubmission`/`SubmitAssignment`/`ReleaseGrade`/`UpdateGrade`/`StoreSubmissionFile`
  actions; `GradeSubmissionJob`; student `MyAssignmentController` + admin `AssignmentController`
  + `GradingController` (+ Requests); `config/assignments.php`; `grade_ready` template; frontend
  assignment submit page, My-grades list, admin rubric builder + grading queue.
- **Deferred (owner-visible, not gaps):** append-only resubmission history (one active
  submission this pass); rubric weighting beyond per-criterion max; peer review;
  plagiarism/similarity beyond the advisory ai_likelihood; inline file preview (download only).
  Next: **P3.4c — certificates** (branded HTML via the ReceiptRenderer seam + QR + public
  verify), then **P3.5 — Content AI + reports**.
