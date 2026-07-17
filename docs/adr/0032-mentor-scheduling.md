# ADR 0032 — P4.4 Native mentor & interview scheduling

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD §6.11 — fully native scheduling (no Calendly): profiles,
  recurring IST availability, combined calendar, booking → Zoom →
  notifications → reminders, the 4h rule, no-shows, feedback → PRI, ratings.
  Placement interviews reuse the engine via `purpose`.

## Decisions

**One deterministic slot engine, used by display AND booking.** `SlotFinder`
cuts slots from weekly IST windows (minutes-of-day, weekday rows) minus
dated exceptions (full-day or window) minus booked sessions minus the
4-hour lead — and `BookMentorSession` re-validates through the same math
under a row lock, so the calendar can never show what booking rejects and
a double-book race loses cleanly (the loser keeps their credit; consume
happens inside the same transaction as the conflict check).

**Booking answers instantly; Zoom rides the queue.** The request creates the
session and returns; `FinalizeMentorBooking` (queued, per CLAUDE.md) creates
the Zoom meeting, notifies BOTH sides (WhatsApp/email via Messenger +
dashboard cards), and arms T-24h/T-1h `SendMentorReminder` jobs. Reminders
are sync-driver-safe: each phase fires only inside its window, only for a
still-booked session whose start time matches what was armed, once — so
cancels and reschedules silently kill stale reminders instead of chasing
them. The .ics invite is a student endpoint (RFC 5545 plain text, zero
dependencies).

**Credits: the student only ever loses one by not showing up.** Booking
consumes 1 `mentor` credit (wallet + ₹499 `mentor-extra` product exist since
P2.8; empty wallet → 402 with one-tap top-ups, same pattern as voice).
Cancels (student ≥4h notice; staff any time) and mentor no-shows refund;
a reschedule keeps the credit spent and moves the session (Zoom updated in
place — the join link survives). Every movement is one auditable
`credit_transactions` ledger.

**Feedback is the human calibration of PRI.** Post-session the mentor scores
readiness 0–100 with strengths/improvements → session completes, telemetry
(`ActivityType::MentorFeedback`) logs, and ScoreCalculator blends the
average feedback score into PRI (`scoring.pri.mentor_blend`, 0.10; no
feedback = byte-identical PRI). Student ratings (1–5) aggregate into the
mentor's public rating.

**The coach recommends humans only after machines have tried.**
`NextActionKey::BookMentor` fires when a student has ≥2 weak modules AND a
completed mock — "persistent weakness" means they practised and are still
stuck, so the P3.2 action ladder is unchanged for everyone without a mock.

**Ownership is the mentor's gate; placement owns the pool.** The mentor hub
(sessions, feedback, no-show, availability editor) is gated purely by owning
an active `mentor_profiles` row — no new permission. Admin CRUD of the pool
sits under `can:manage-placements`. Admins decide WHO mentors; mentors
decide WHEN.

**Google Calendar busy-sync is schema-ready, deferred.** `google_calendar_id`
exists on the profile; the sync job lands when Google credentials arrive
(founder input) — exceptions cover the manual path meanwhile.

## Consequences

- 18 Pest tests in `tests/Feature/Mentoring/`: slot math (windows,
  exceptions, bookings, lead time), tag filtering, booking side-effects,
  402 top-up, double-book race, off-grid rejection, cancel/4h-rule/refund,
  reschedule + stale-reminder guard, reminder idempotence, both no-show
  policies, feedback → PRI, ratings, .ics, hub gating + availability
  round-trip, cross-mentor session isolation, admin gating, and the coach
  recommendation (suite: 600).
- P4.5's placement flow books `purpose: placement_interview` through the
  same endpoints; mentor wallets need an admin/counselor grant path or an
  included-quota policy — flagged as a follow-up policy decision for the
  founder (today: module rewards and paid packs fill the voice wallet, the
  mentor wallet fills via purchases or manual grants).
