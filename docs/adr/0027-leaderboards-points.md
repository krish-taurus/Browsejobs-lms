# ADR 0027 — P3.6 Batch leaderboards & points

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD §6.16 — batch leaderboard ranked by a transparent Prep
  Score, weights admin-configurable, anti-toxicity and anti-gaming by design.

## Decision

**Ledger, not counters.** `points_events` is an append-only ledger with a
unique `(tenant, user, source, source_key)` idempotency key. Source keys are
per *achievement*, not per attempt — `session:{id}`, `quiz:{id}`, `lab:{id}`,
`day:{date}` — so replayed webhooks, quiz retakes, and re-submitted labs can
never re-mint. Leaderboards are computed from the ledger (weekly = current
week's `awarded_on`), never stored.

**Anti-gaming is structural.** Points are minted only inside four verified
server-side flows (attendance webhook finalisation, server-authoritative quiz
grading, Judge0-graded lab submits, the telemetry seam for streaks). There is
no endpoint that writes points. A per-tenant `daily_cap` clamps then blocks
each student's daily total.

**Weights** live in `points_settings` (one row per tenant, on-demand defaults
from `config/points.php`), edited at `/admin/points`. Gated by
`can:manage-monetization` rather than a new permission — same operator persona
as the other commercial settings; revisit if a dedicated role emerges.
`mock_points` is reserved and activates when P4 builds mock interviews.

**Anti-toxicity** per PRD: the API names only the top 10, substituting
"Student" for anyone with `users.leaderboard_opt_out` (self-serve toggle on
the card); everyone else receives only their own rank, points, and
distance-to-next with a coach line ("You're 20 points from #2…").

**Badges** (`student_badges`, definitions in config): Module Ace (100% quiz),
7-Day Streak, First Lab Pass; First Mock Done reserved for P4. Celebrations are
direct `in_app_notifications` to the earner and (fan-out capped, skipped
entirely for opted-out earners) active batchmates — the "batch feed" until a
dedicated feed exists.

**Coach integration** is delivered as the distance-to-next coach line on the
leaderboard card rather than by widening the P3.2 `ScoreCalculator` payload;
`ScoreCalculator::streakDays()` was exposed so points and coach share one
streak definition. Streak evaluation runs only on the first telemetry event of
a student's day (cost control on the hot path). Quiz submissions now also emit
`quiz_submitted` telemetry — closing a P3.1 gap where quiz-only days did not
count toward streaks/engagement.

## Consequences

- Rebalancing weights changes only future awards; history stays honest.
- A found bug fixed en route: date-cast equality (`awarded_on = 'Y-m-d'`)
  never matched SQLite's stored midnight timestamps — daily-cap sums and the
  weekly filter use `whereDate` (regression-tested).
- Deferred: assignment-punctuality points (assignments have no `due_at`
  column yet), per-purpose feeds, a real batch activity feed.
