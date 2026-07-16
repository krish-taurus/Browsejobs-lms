# ADR 0008 — P2.3 Fee ladder + access control: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.8 (escalation ladder / soft-hard blocks / dunning),
  `docs/BUILD-PLAYBOOK.md` P2.3

## Context

P2.2 left `FeeGate` as `AllowAllFeeGate` and the `PaymentCaptured`/`PaymentFailed`
events with no consumers, for P2.3. This slice builds the dunning engine:
reminder ladder, soft/hard access blocks, instant unblock on payment, an admin
dunning dashboard, and a student fee widget + blocked screen. Founder-confirmed:
PRD-default timing (reminders 7/3/1/0 days before due; 5 grace days → soft block;
+7 days → hard block) and the gate-plus-student-widget scope.

## Decisions

1. **The ladder is a daily scheduled command, not delayed jobs.**
   `fees:run-ladder` (scheduled `dailyAt('07:00')` in `routes/console.php`)
   re-evaluates each active plan's earliest unpaid instalment against `due_on`
   every day — state-driven and robust to a missed run. Delayed jobs (P1.6's
   class-reminder ladder) suit short precise SLA timers; a multi-day dunning
   horizon wants a re-entrant batch. Idempotency comes from the
   **`fee_reminders`** table (unique `(instalment_id, rung)`), so each rung
   (`t-7`/`t-3`/`t-1`/`due`/`grace-N`) sends exactly once no matter how often the
   command runs. Grace reminders carry the day number so they fire daily.

2. **The real gate is `DuesFeeGate`** — denies live/recordings access iff the
   student has an active `access_blocks` row. It replaces the `AllowAllFeeGate`
   binding at the single consumer (`JoinLiveSession`); an enrolled student with
   no block still passes, so existing behaviour is preserved (the P2.2 gate test
   was updated to assert allow-when-clear / deny-when-blocked). `AllowAllFeeGate`
   is kept for tests that force-allow.

3. **Blocks are their own lifecycle** (`access_blocks`, PRD §8): a `soft` block
   locks classes + recordings; it **promotes** to `hard` (fee-screen-only) after
   the hard-block window, which also raises a **counselor task** (P2.1
   `CreateTask`) against the student's matching CRM lead (correlated by
   normalized phone/email; best-effort). `ApplyAccessBlock` is idempotent (one
   active block per student; equal-or-lower request is a no-op).

4. **Instant unblock is a job dispatched from `MarkInstalmentPaid`**
   (`LiftFeeBlocks`, alongside `FlipEnrolment`) — consistent with the repo's
   dispatch-jobs-from-actions convention. It lifts all active blocks once the
   student has **no remaining overdue instalment** (so paying the overdue EMI
   restores access even with future instalments outstanding). Idempotent.

5. **Fee reminders use a new `FeeNotifier` interface + `LogFeeNotifier` stub**
   (`SessionNotifier` is `LiveSession`-typed, so not reusable). Real WhatsApp/
   email binds in P2.4.

6. **The student fee surface reads a new `GET /me/fee-status`** (authenticated,
   non-admin; runs in the student's tenant context). It backs the dashboard fee
   widget (countdown + escalating banner + Pay-now) and, on a **hard** block, a
   full-screen lockout rendered by `PortalShell`. Escalation colours stay within
   the token rules (info=`sky`, warning=`sky`+trust accent, urgent=`warn/10`,
   overdue=`warn` solid) — **`amber` is not used** (reserved for stars/coach).

## Consequences

- A student misses an instalment → reminded → soft-blocked → hard-blocked (with a
  counselor task); the instant they pay, `MarkInstalmentPaid` fires `LiftFeeBlocks`
  and access restores — zero manual steps (the Phase-2 gate).
- The command is the first scheduled task; production still needs a cron/worker
  to run `fees:run-ladder` daily (an outstanding ops input, not code).
- SQLite (test DB) can't add FK via `ALTER`, but both new tables are fresh
  creates, so every FK is real.
- Net-new student live-join / recordings *delivery* endpoints remain deferred to
  the live-classes milestone; enforcement today is server-authoritative on
  `JoinLiveSession` + the portal's block-aware rendering.
