# ADR 0029 — P4.1 AI mock interviewer core (text mode)

- **Status:** Accepted (opens Phase 4)
- **Date:** 2026-07-17
- **Context:** PRD §6.6 — transport-agnostic mock interview engine. Voice
  transport + per-batch quotas arrive in P4.3; the real-interview question
  bank enriches questioning in P4.2.

## Decisions

**Blueprints are curriculum, sessions are conversations.** One
`mock_blueprints` row per course (role title, scorecard competencies, opening
question) managed under `can:manage-curriculum` at /admin/mocks; sessions are
`mock_interviews` + `mock_turns` mirroring the tutor's conversation shape so
P4.3's voice mode reuses the same records with `mode='voice'`. Starting is
deterministic (the blueprint's opening question, zero AI cost); tokens spend
only when the candidate actually engages.

**First consumer of `text_practice_enabled`.** The whole feature — start
endpoint, nudge listener, hub page — gates on the monetization flag (default
off, per founder's P3.4 pricing decision). Flipping one switch in
/admin/monetization launches text mocks per tenant; no deploy.

**Answer + follow-up is one transaction.** The candidate turn and the AI
follow-up question commit together; a budget/transport failure rolls both
back, so a retry never stores duplicate answers. The adaptive prompt
(`mock_interview.v1`) sees the full transcript and remaining-question count,
asks EXACTLY ONE question, and stops at `mocks.max_questions` (6) — the
scorecard, not endless chat, is the product.

**Scorecards fail safe and score conservatively.** `mock_scorecard.v1`
demands strict JSON (competency scores, strong/weak moments, model answers,
exactly 3 actions; job/salary promises forbidden). Any parse/budget/transport
failure yields a deterministic fallback card at overall 40
(`scorecard_source: fallback`) — an ungraded session can never inflate PRI or
unlock the human-mock gate, and staff can see fallback cards at /admin/mocks.

**Mock signal blends into PRI only once it exists.** With no completed mock,
PRI is byte-identical to P3.2 (`scoring.pri.mock_blend` contributes nothing).
After the first completion: `pri = (1-blend)·pri + blend·bestMockOverall`
(blend 0.15). Best score ≥ `mocks.human_gate_score` (70) surfaces
`human_mock_unlocked` — the AI mock is the qualifier for the human mock, per
the PRD's escalation ladder. Completion feeds the existing engines:
`ActivityType::MockCompleted` telemetry, `PointsSource::Mock` (idempotent per
interview, daily-capped) and the reserved `first-mock-done` badge.

**Nudges ride the topic flag.** `topics.mock_enabled` (admin checkbox in the
curriculum tree) + TopicCompleted → queued `DispatchMockNudge`: WhatsApp
utility template `mock_nudge` with a magic link plus an in-app card. Fires
once per user+topic by construction (the event itself is once-only) and stays
silent when the tenant flag is off.

## Consequences

- 14 Pest tests in `tests/Feature/Mocks/` cover flag gating, resume, the
  adaptive loop + cap, scorecard parse/fallback/budget paths, idempotent
  points/badge, PRI blend isolation, the human gate, nudge on/off, cross-
  tenant isolation and admin CRUD (suite: 552).
- `MockBlueprintSeeder` gives every live course a sensible default blueprint,
  so enabling the flag is the only launch step.
- P4.2 will source questions from the interview bank behind the same
  blueprint model; P4.3 adds voice transport + quota consumption at start.
