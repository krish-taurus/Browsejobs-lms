# ADR 0014 — P3.1 AI gateway + telemetry + coding labs: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.4/§6.5/§7 + CLAUDE.md "AI Service Layer" + BUILD-PLAYBOOK P3.1 (Phase 3 opener).

## Context

The three infrastructure pillars the whole AI Coach layer stands on:
1. an `app/Services/AI` Anthropic gateway (versioned prompts, `ai_events` cost log,
   per-student daily token budget, rate limiting);
2. an `activity_events` telemetry stream (logins, watch time, lesson/topic/module
   completion, code metrics);
3. Monaco + self-hosted Judge0 coding labs emitting `code_submissions` + telemetry.

**Scope boundary (playbook):** P3.1 is infrastructure only — **no student-facing AI
feature ships**. The consumers (Coach Panel + scoring P3.2, AI Tutor RAG P3.3,
assessment automation P3.4, support-desk AI P3.5) call this gateway + read this
telemetry later.

**Founder-confirmed scope (this session):** Judge0 runs **synchronously**
(interactive); the AI layer is **pure infra + an admin cost view** (no student AI
feature); telemetry is **ingested now**, with DPDP consent capture deferred.

## Decisions

1. **Gateway = service over a swappable transport.** `AiGateway` (concrete,
   injectable) composes an `AiClient` interface (`AnthropicClient` real /
   `FakeAiClient` test), a `PromptRepository` (versioned files), the budget check, and
   `ai_events` logging. Tests bind `FakeAiClient` and exercise the **real** gateway, so
   budget + cost logging + prompt loading are all under test. `AiPurpose` tags every
   call. Every mutation wraps in `TenantContext::run`.
2. **Daily token budget ≠ purchasable quota.** Enforced in the gateway by summing
   today's `ai_events.total_tokens` for the user (`AI_DAILY_TOKEN_BUDGET_PER_STUDENT`,
   default 200k); over-budget throws `AiBudgetExceeded` + logs a `budget_exceeded`
   event. A transport error logs a `failed` event and rethrows.
3. **Prompts are versioned files, never inline** (CLAUDE.md): `resources/prompts/
   {name}.v{n}.md` with `{{var}}` placeholders; seeded `tutor.v1` + `ping.v1`.
4. **Cost in integer micro-rupees** (`cost_micros`; ₹1 = 1e6) for token-level
   precision; pricing per-Mtok in `config/ai.php`. Money-as-integers preserved.
5. **Telemetry via a central `RecordActivity` + queued listeners** on existing events
   (`TopicCompleted`/`ModuleCompleted`/`Login` — students only) + a validated client
   ingest (`POST me/activity`, allowlisted `watch_time`/`lesson_viewed`, throttled).
   Ingestion is unconditional now; DPDP consent gating deferred. Quiz-attempt telemetry
   deferred (no quiz model until P3.4).
6. **Coding-lab content is a companion `coding_labs` table** (one per coding-lab lesson;
   `LessonType::CodingLab` already existed), not new lesson columns. Judge0 runs
   **synchronously** (interactive I/O the student awaits) via a `Judge0Client` trio;
   `RunCode` stores a `code_submission` and emits code telemetry. **Hidden test
   expected-outputs never reach the student** (the student resource exposes only a
   count; the admin resource shows them).
7. **No new permission.** The read-only AI-usage dashboard is gated under
   `manage-monetization` (cost oversight); coding-lab content admin under
   `manage-curriculum`. Student lab/activity endpoints live in the `me/*` group
   (tenant-scoped by wrapping in the student's context; foreign lesson ids 404).
8. **Judge0 is opt-in in docker** (`--profile labs`: server + workers + its own
   postgres, reusing redis) so the default stack stays light; the server needs
   privileged mode + cgroups. Tests fake Judge0; the real client is bound from config.

## Consequences

- Verified live on a seeded scratch DB: an AI call logs an `ai_event`
  (tokens/cost/latency) → the daily budget trips with a `budget_exceeded` event →
  `TopicCompleted` records telemetry → a lab submit grades against Judge0 (faked) +
  emits `code_submitted` activity → the AI-usage dashboard aggregates.
- New models/tables: `ai_events`, `activity_events`, `coding_labs`, `code_submissions`;
  `AiClient`/`Judge0Client` bindings + `config/ai.php`, `config/coding_labs.php`, and
  `services.php` anthropic/judge0 blocks; a tight `ai` rate limiter. `Module`/`Topic`/
  `Lesson` gained factories.
- **Deferred (owner-visible):** the AI *consumers* (Coach Panel + scoring **P3.2**;
  AI Tutor RAG **P3.3**; assessment automation **P3.4**; support-desk AI **P3.5**) call
  this gateway + read this telemetry later; the first **queued** AI job arrives with
  the first consumer; DPDP telemetry-consent capture/opt-out UI (compliance milestone);
  quiz-attempt telemetry (P3.4); lab enrolment-gating; a self-hosted Monaco bundle
  (the editor loads from CDN in dev); the admin coding-lab content editor UI (the API +
  tests exist). Standing ops note: production cron runs `fees:run-ladder`,
  `conversions:run-nudges`, `support:check-sla`; Judge0 runs under the `labs` profile.
