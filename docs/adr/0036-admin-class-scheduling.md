# ADR 0036 — Admin live-class scheduling, reschedule & cancel

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.3 (live classes, reminders, cancel/reschedule notifications)
- **Relates to:** P1.5 (Zoom), P1.6 (reminders/cancel-reschedule engine), the batch/roster admin

## Context

The live-class engine shipped in P1.5/P1.6 — `ScheduleLiveSession`,
`RescheduleLiveSession`, `CancelLiveSession`, the reminder ladder, the `session_changes`
log, and (for reschedule/cancel) automatic notification of the whole batch through the
messaging hub. All of it was tested but **wired to nothing**: no controller, no route, no
admin UI. Sessions could only be created programmatically or in tests.

This is the first slice of a founder-requested batch of operational features. It also
resolves two things the founder asked for that turned out to already exist under the
hood: "reschedule/cancel a class → email/message goes out to the batch" (the engine
already does this), and "schedule a temp batch for extra sessions, recording rolls into
the parent batch."

## Decision

1. **Surface the existing engine; add no business logic.** `LiveSessionController`
   (gated `can:teach-classes`) exposes list / schedule / reschedule / cancel and delegates
   straight to the P1.6 Actions. The auto-notify-the-batch behaviour on reschedule/cancel
   is the engine's, unchanged.

2. **No "temp/sub-batch" entity.** An extra session is just another `LiveSession` on the
   batch. Recordings already attach to the batch via `recording → live_session → batch`
   (the Zoom `recording.completed` webhook), so an ad-hoc session's recording lands in
   that batch's library automatically. Building a parent/child batch concept would have
   duplicated a relationship that already exists. The founder confirmed this reframing.

3. **`teach-classes` granted to the `admin` role.** Only `trainer` and `super-admin` held
   it; an institute admin scheduling classes is expected, and the batch-detail page they
   already use is where scheduling lives. One-line seeder change.

4. **A cancelled or ended class cannot be rescheduled or cancelled again** (409). The
   whole point of the action is the notification it sends; notifying a batch about a
   change to a class that already happened or was already called off is noise that erodes
   trust in the channel.

5. **Reason is required on reschedule and cancel.** It is shown to students in the
   notification — "moved to Saturday, trainer travelling" is only useful with the why.

6. **Raw Zoom URLs stay server-side.** The resource exposes `has_meeting` (whether the
   meeting exists yet), never the join/start URL — students reach a class only through the
   gated join flow, as the schema comment has required since P1.5.

## Consequences

- **Positive:** Admins and trainers can now run the class schedule from the batch page,
  and reschedule/cancel fires the batch notification that was already built. Extra
  sessions need no special handling.
- **Trade-offs:** The student-facing class list and the join button are still stubs
  (a later slice); this slice is the staff side only. Zoom is still one global account —
  the "multiple Zoom licenses allocated per mentor" requirement is a separate slice, as is
  auto-recording (Zoom is not currently told to record).
- **Deferred (owner-visible):** per-mentor Zoom license pool + auto-record; the student
  class/recordings page with downloadable notes/practice; menu grouping; the admin
  Settings area for keys. These are the remaining slices in this batch of work.

## Verification

`php artisan test` — full suite **637 passing** (main's 628 + 9 new), incl.
`Feature/Admin/LiveSessionAdminTest`: schedule a class; list newest-first; reschedule
**and the whole batch is notified with the reason** (asserted via a spy notifier); cancel
**and the whole batch is notified**; 409 on rescheduling an already-cancelled class;
future-time validation; `teach-classes` required (a mentor is denied); trainers allowed;
cross-tenant 404. Pint clean. `npm run typecheck`, `lint`, `build` pass. Fresh
`migrate --seed` (`LiveClassSeeder`) puts one upcoming class and one past class with a
stored recording on the seeded paid batch, so the schedule and the "recording in the
batch library" story are demo-able.

**Note on process:** this slice was built while a second Claude session was working
P4.6a-retention in the same working tree; the two were untangled by committing P4.6a to
its own branch first, then rebasing this work onto `main`. The durable fix is one git
worktree per session.
