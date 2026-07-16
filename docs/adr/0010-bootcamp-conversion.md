# ADR 0010 — P2.5 Bootcamp conversion automation: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §5 Stage 2–3 + §6.10, BUILD-PLAYBOOK P2.5 (the Phase-2 finale)

## Context

P2.5 is almost entirely *wiring* the earlier milestones into the conversion flow:
a bootcamp completes → attendees auto-assign to the linked paid batch as
**Reserved — Payment Pending** and their CRM lead flips to `reserved`; an admin
raises a fee plan whose **payment link auto-delivers** via the P2.4 Messenger; a
**non-payer nudge ladder** chases the unpaid, auto-pausing on reply; and a
**funnel dashboard** reports ad→masterclass→bootcamp→paid by UTM. First-instalment
capture still auto-enrols + unblocks via the P2.2/P2.3 chain — closing the
Phase-2 gate. Founder-confirmed: auto-convert **assigns to Reserved only** (admin
picks the plan; the link auto-sends when generated); nudge cadence **24h/72h/7d +
a 72h counselor task**.

## Decisions

1. **Conversion is lean + idempotent.** `ConvertBootcampAttendee` reuses
   `AddStudentToBatch` (paid batch, Reserved — the one-membership guard makes
   re-runs no-ops) + `MoveLeadStage` to the seeded `reserved` stage (lead
   correlated by normalized phone/email, like `FlipEnrolment`) + audit
   `conversion.assigned`. `CompleteBootcamp` sets `status='completed'` and
   converts every occupying attendee, auditing `batch.completed`. **No fee plan
   is created on convert** (founder: admin picks single vs EMI).
2. **Payment-link delivery is now inside `SendPaymentLink`** (P2.2's deferred
   TODO): after storing the Razorpay link it calls `Messenger::send($student,
   'payment_link', …)`. So every link — single, EMI, bulk — auto-delivers, and
   the delivery **no-ops gracefully** when no `payment_link` template exists (P2.2
   link tests unchanged).
3. **The nudge ladder mirrors `RunDunningLadder`** — a daily
   `conversions:run-nudges` command over active fee plans whose batch is **paid**
   and whose member is still **Reserved/Payment-Pending** (converted-but-unpaid).
   `converted_at = fee_plan.created_at`; rungs at config `[1,3,7]` days, deduped
   via `conversion_nudges (fee_plan_id, rung)`. Each nudge **re-sends the payment
   link** (this *is* the abandoned-checkout recovery) with a seats-left/starts
   scarcity line; a **counselor task** is raised at day 3. **Auto-pause**: skipped
   if the correlated lead's `last_replied_at` is after conversion (the P2.4
   reply seam). **Stops automatically** on payment — first-instalment capture
   flips the member to Enrolled, dropping it from the query.
4. **The funnel dashboard aggregates from lead stages** — the CRM pipeline *is*
   the funnel. `FunnelController` counts non-merged leads at/past each milestone
   (Lead→Masterclass→Bootcamp→Reserved→Enrolled=paid) and groups by
   `utm_campaign` with `enrolled+` as the paid count. No `funnel_events` table
   (PRD names it; P1.7 never built it — deferred), and creative-level needs a
   `utm_content` column we don't capture — also deferred.

## Consequences

- Phase-2 gate flows end-to-end: convert → link auto-sent → nudged → paid →
  enrolled + unblocked, zero manual steps once the admin raises the plan.
- New scheduled command `conversions:run-nudges` (`dailyAt('08:00')`); production
  still needs the cron to run it (ops input, not code). New `CrmTask` source
  `conversion`; `CreateTask` gained an optional `$source`.
- SQLite (test DB) can't add FK via `ALTER`, but `conversion_nudges` is a fresh
  create; the `Batch.linked_source_batch_id` FK already existed (P1.4).
- **Deferred (owner-visible):** the Review-for-Voucher engine (**P2.6**);
  `funnel_events` + creative-level (utm_content) tracking; the AI lead score
  during bootcamp (**P3**). **This closes Phase 2.**
