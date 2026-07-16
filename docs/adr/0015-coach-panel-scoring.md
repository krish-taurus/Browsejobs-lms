# ADR 0015 — P3.2 Coach Panel + student scoring: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.4/§6.10/§7 + CLAUDE.md + BUILD-PLAYBOOK P3.2 (first consumer of the P3.1 telemetry stream).

## Context

P3.1 shipped the telemetry stream (`activity_events`, `code_submissions`,
`topic_completions`, `attendance`) but **no consumer**. P3.2 is the first: turn those
raw signals into per-student **mastery map, engagement, dropout + placement risk, and
PRI**, then surface them as the two faces of the AI Coach — the student **Coach Panel**
(dashboard centerpiece: one Next Best Action, PRI ring + trajectory, wins/focus,
streak) and the counselor **Risk dashboard** (at-risk sorted, daily movers, red flags,
intervention scripts).

**Founder-confirmed scope (this session):**
- **Fully rule-based.** Scores, the Coach Panel narrative (went-well / needs-work +
  Next Best Action), and the counselor intervention scripts are all deterministic /
  templated — **zero token spend**. The P3.1 AI gateway's first real consumer becomes
  the AI Tutor (P3.3), not this milestone.
- **Defer the lead-scorer rebind.** The CRM `LeadScorer` stays rule-based as-is; P3.2 is
  enrolled-student scoring + panels only.

**Scope boundary (playbook):** §7 — scoring is **auto-shown** to staff; the human
checkpoint is on the *action* (the counselor decides to call), not the display. The
placement flag stays human-gated: P3.2 computes/shows PRI, it does **not** gate the
placement pool.

## Decisions

1. **Two tables: `student_scores` (latest, 1/student) + `score_snapshots` (daily
   history).** `student_scores` (engagement, risk_dropout, risk_placement, pri,
   streak_days, mastery json, next_action json, red_flags json, computed_at) is the
   counselor dashboard's source + the panel cache; it stands in for the PRD §8
   `students(risk, pri, engagement)`. `score_snapshots` (unique tenant+user+captured_on)
   powers the **PRI trajectory** and the counselor **daily movers**. The mastery map is
   derived, stored as json — no per-module cache table.
2. **Rule-based `ScoreCalculator` (deterministic, config-weighted).** A pure service
   reads the raw signals → engagement (recency/consistency/volume), streak, mastery per
   module (`topics_done/total`), attendance avg, fee flag → PRI, dropout risk, placement
   risk, went-well/needs-work, and the single Next Best Action. All weights + thresholds
   live in `config/scoring.php`; **no LLM** (founder). `red_flags` are computed here and
   stored, so the counselor dashboard reads them rather than re-deriving.
3. **Freshness = nightly batch + live panel.** `scores:recompute` (daily 06:00,
   per-tenant over occupying students — mirrors `RunDunningLadder`) keeps
   `student_scores` fresh for the counselor: the disengaged never open the panel yet are
   the highest risk, so the batch is essential. The Coach Panel **also** computes live on
   load (`GET me/coach`) and persists, so a student never sees a stale number. The
   counselor dashboard reads the stored rows; movers = today's `risk_dropout` vs the most
   recent earlier snapshot.
4. **Next Best Action = rule priority** (first applicable): (1) fee blocked/overdue →
   clear dues; (2) weakest module below the weak threshold → practice it; (3) broken
   streak / low engagement → jump back in with a lab; (4) PRI ≥ ready threshold → book a
   mock; else → complete your next topic. Went-well = strong modules; needs-work = weak
   (rendered dominant). PRI = `0.50·mastery + 0.30·engagement + 0.20·attendance`;
   dropout risk = `0.55·(100−engagement) + 0.25·stalled + 0.20·fee`; placement risk =
   `0.60·(100−mastery) + 0.40·(100−PRI)`. All scores clamped to 0–100.
5. **Counselor intervention scripts are canned, keyed to the top red flag**
   (fee → stalled → disengagement → low attendance → on-track) — rule-based, no AI. The
   counselor copies and makes the call (§7).
6. **No new permission.** The Coach Panel lives under `me/*` (tenant-scoped by wrapping
   in the student's context); the counselor Risk dashboard under `can:manage-leads`
   (`admin` and `counselor` both hold it). P3.2 also **wires the real dropout risk into
   the P2.7 Support Desk sidebar** (`TicketStudentContext`), closing that deferral.

## Consequences

- Verified on a seeded scratch DB: `ScoringSeeder` gives one student a real completion
  history + a 6-day login streak (healthy Coach Panel) and rescoring everyone, so the
  Risk board surfaces the disengaged/fee-blocked at risk 75 with `fee_overdue` +
  `low_engagement` flags and a fee-empathy script. `GET me/coach` returns the computed
  panel + a persisted snapshot; `GET admin/risk` returns the risk-sorted list + movers,
  denies non-`manage-leads` staff, and is tenant-scoped.
- New: `student_scores` + `score_snapshots` (+ models/factories); `config/scoring.php`;
  enums `MasteryBand` / `RiskBand` / `NextActionKey`; `ScoreCalculator` +
  `InterventionScript` services; `ComputeStudentScores` / `RecomputeAllScores` actions +
  the `scores:recompute` command (scheduled daily); `CoachController` + admin
  `RiskController`; `User::batchMemberships()`. Frontend: `CoachPanel` (PRI ring +
  sparkline) on the student dashboard, the `admin/risk` board + nav entry.
- **Deferred (owner-visible, not gaps):** AI-drafted coach narrative + intervention
  scripts (kept rule-based this milestone); the telemetry-aware CRM lead-scorer rebind;
  quiz/mock signals in mastery/PRI (quizzes land **P3.4**, mocks **P4.x** — the
  calculator leaves weighted slots); placement-pool gating (human-checkpointed, later).
  Attendance in scores relies on P3.1's Zoom-fed `attendance` rows; with none present it
  contributes 0. Next: **P3.3 — AI Tutor (RAG)**, the AI gateway's first real consumer.
  Standing ops note: production cron now also runs `scores:recompute` (alongside
  `fees:run-ladder`, `conversions:run-nudges`, `support:check-sla`).
