# ADR 0021 — P3.5a reports & digests: design decisions

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD v1.4 §6.10 + BUILD-PLAYBOOK P3.5 (the reports & digests slice).

## Context

P3.5 bundles six AI sub-systems; this milestone builds the **reports & digests** slice —
three AI-narrated summaries over the P3.2 scoring/risk data: a **weekly student report**
(went-well / needs-work / one focus), a **trainer pre-class brief** (~30 min before a
session), and a **counselor daily digest** (risk movers + scripts). It's the highest-reuse
slice of the phase: the numbers already exist, this adds the prose wrapper + delivery.

**Founder-confirmed scope (this session):** reports & digests only (content pipeline,
syllabus PDF, and support-desk AI are separate follow-ups); **AI-narrated with a rule-based
fallback**; **in-portal HTML + magic link, no PDF**; **per-recipient billing**.

## Decisions

1. **One `reports` table** (recipient_id, `type` [weekly_student|trainer_brief|
   counselor_digest], title, narrative, `data` json snapshot, period_start/end, subject
   morph, ai_event_id, read_at). Weekly + digest idempotency at the DB via
   `unique(recipient_id, type, period_start)` + `updateOrCreate` (keyed on a **Carbon**
   period, not a string — the date-cast gotcha would otherwise re-insert). Trainer briefs
   carry a null period and dedupe **in code** by `subject_id` (a partial unique isn't
   SQLite-portable; multiple NULL periods are allowed).
2. **`ReportNarrator` — AI with a deterministic fallback.** `narrate($billTo, $type, $vars)`
   calls the gateway (`AiPurpose::Report`, per-type prompt) inside `try/catch(AiBudgetExceeded|
   Throwable)` → on any failure or empty output returns a templated `fallback()`. It
   **never throws**, `sanitize()`s (strip control chars, cap 2k chars), and best-effort
   captures `ai_event_id`. So a report is always produced and delivered.
3. **Weekly report** (`GenerateWeeklyReport` → `GenerateWeeklyReportJob` → per-tenant
   `RunWeeklyReports` + `reports:weekly`, Monday 07:30): skip students not in an occupying
   batch; narrate the ScoreCalculator data (**bills the student**); `updateOrCreate` the
   week's report; deliver **only on create** (`wasRecentlyCreated`) via a `weekly_report`
   magic link (`report.view` → `/reports/{id}`) + an in-app notification.
4. **Counselor digest** (`BuildCounselorDigest` — extracted from `RiskController`, which now
   calls it, so the payload is computed once — + `GenerateCounselorDigest` +
   `digest:counselor-daily`, 06:30): per counselor (staff with `manage-leads`); **skip when
   no high-risk/movers and when a tenant has no counselor**; narrate (**bills the counselor**)
   → report + inline-narrative message + in-app.
5. **Trainer brief rides the reminder rail.** `ArmSessionReminders` also dispatches
   `SendTrainerBrief($sessionId, $token)` at `scheduled_start − brief_offset_minutes` (30)
   stamped with the **shared rotating `reminder_token`**, so a reschedule/cancel rotates the
   token and the stale brief is a no-op — no new guard invented. `GenerateTrainerBrief` skips
   when the batch has no trainer; narrates the roster + at-risk students + topic (**bills the
   trainer**); delivers to `batch.trainer`; idempotent per session.
6. **Per-recipient billing + never-throw.** The gateway bills whichever `User` is passed;
   each report bills its recipient. The narrator fails safe and every job also swallows
   `Throwable`, so a scheduled loop never aborts on one recipient.
7. **In-portal delivery, no PDF/s3.** Student weekly reports render on the portal `/reports`
   page from the DB row (narrative + `insights`); the magic link lands there. Briefs/digests
   are staff pushes (message + in-app), no page this milestone. A resource field named `data`
   would collide with Laravel's JSON wrapper, so the student resource exposes it as `insights`.

## Consequences

- Verified on a seeded scratch DB + 14 Pest cases: the weekly report is AI-narrated,
  delivered, and billed to the student; **a budget-exceeded run falls back to a templated
  narrative and still delivers**; a non-occupying student is skipped; re-running is
  idempotent (one row, one message); the counselor digest is built from the same
  RiskController payload (regression-safe), bills the counselor, and skips when there's no
  risk; the trainer brief arms at −30 min with the session's token, a stale token no-ops,
  and it bills the trainer; `me/reports` is auth + cross-tenant scoped, marks read, and hides
  staff briefs; the `report.view` magic link lands the student on their report.
- New: `reports` table + `Report`/`ReportType`/factory; `ReportNarrator` +
  `report`/`trainer_brief`/`counselor_digest` prompts; weekly/digest/brief actions + jobs +
  the `reports:weekly` and `digest:counselor-daily` commands (scheduled); `BuildCounselorDigest`
  (RiskController extracted); `ArmSessionReminders` brief offset + `SendTrainerBrief`;
  `MyReportController` + `ReportResource`; `weekly_report`/`trainer_brief`/`counselor_digest`
  templates; `config('app.frontend_url')` was already present; frontend `/reports` list + view.
- **Deferred (owner-visible, not gaps):** the rest of P3.5 — content pipeline
  (recording→transcript→notes/flashcards/draft-quiz, needs a transcription approach),
  AI syllabus generator + PDF (no PDF engine), support-desk AI (needs a sentiment field);
  also real PDF export of reports and a staff report/brief history page. Then P3.6 leaderboards,
  P3.7 motivation/Market Pulse/Content Hub.
