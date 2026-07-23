# ADR 0043 — Mentor area rework: direct-connect 1:1s, weekly batch mentoring, Zoom license rotation

- **Status:** Accepted
- **Date:** 2026-07-23
- **PRD:** §6.3 (live classes, Zoom), §6.11 (mentor scheduling)
- **Relates to:** ADR 0032 (mentor scheduling), ADR 0036 (admin class scheduling),
  ADR 0040 (Zoom license pool)
- **Context:** Founder decisions (2026-07-23): 1:1 mentor sessions need no Zoom —
  the mentor connects with the candidate directly; the weekly batch mentorship /
  doubt-clearing session should reuse the existing class engine; and the two Zoom
  licenses must rotate automatically across whoever is teaching, not be dedicated
  to individual trainers.

## Decisions

**1:1 mentor bookings are direct-connect — no Zoom meeting is ever created.**
`FinalizeMentorBooking` no longer touches Zoom; it only notifies both sides and
arms the T-24h/T-1h reminders (unchanged). The mentor hub now returns the
student's phone/email on booked sessions, so the mentor reaches out at the slot
(tel:/mailto: links in the UI). The student sees "your mentor will contact you
directly"; the .ics invite says the same. Everything else in ADR 0032 stands:
credits, the 4-hour rule, no-shows, feedback → PRI, ratings. Legacy sessions
booked before this change may still carry a meeting — cancel/reschedule keep
their queued Zoom cleanup for those only, and the UI keeps showing their links
until they finish.

**The weekly batch mentorship session is a live-class `kind`, not a new object.**
`live_sessions.kind` (`class` | `mentoring`) plus an explicit `host_user_id`
put the doubt-clearing session on the same engine as classes: auto Zoom meeting,
reminder ladder, cloud recording into the batch library, batch-wide
reschedule/cancel notifications, and the Start button. The host must be staff
actually allocated to the batch (lead trainer, module trainer, or batch mentor) —
enforced at scheduling, so a session can never be handed to a stranger or another
tenant's user. Hosting rights follow the override: `StartLiveSession` and the
teaching board treat the explicit host as the session's teacher; admins can
always host. The start endpoint moved out of `can:teach-classes` (mentors don't
hold it) — it self-gates on the assigned host and rejects non-staff. The
scheduler UI (single + series) grows a type selector and a host picker drawn from
the batch's mentors; topic-mapping is disabled for mentoring series.

**Zoom licenses auto-rotate; nothing is dedicated to a person.** The per-mentor
allocation from ADR 0040 is retired (the `mentor_id` column stays, unused —
migrations are never edited). When `EnsureZoomMeeting` runs, the session claims
the first active license with no other claimed, still-live/scheduled session
overlapping its time window; `live_sessions.zoom_license_id` records the claim
and doubles as the rotation lock, taken under a row lock on the license pool so
two queued jobs can't claim the same license for overlapping sessions. A
cancelled session stops counting as a conflict, freeing its license. All
licenses busy (or none) falls back to the configured default host exactly as
before. With two licenses, any two overlapping sessions get separate hosts —
regardless of who teaches them.

## Consequences

- **Positive:** 1:1s work without any Zoom dependency or license pressure; the
  weekly mentorship ride gets recordings/reminders for free; two licenses now
  cover every two-way overlap instead of only the two allocated trainers.
- **Trade-offs:** the mentor must actually reach out for 1:1s (no meeting link
  safety net) — reminders on both sides mitigate; license conflict detection
  uses planned windows (start → planned end), so a class running long past its
  planned end could, in the worst case, collide on Zoom with the next claimant.
- **Deferred:** in-app call/chat for 1:1s; per-license usage stats in the admin
  pool view.

## Verification

Pest: `Mentoring/MentoringTest` updated (booking creates no Zoom; contact
surfaces to the mentor; legacy-session Zoom cleanup still works),
`Admin/ZoomLicenseTest` rewritten around rotation (overlap → different licenses,
reuse after a gap, busy → default-host fallback, cancelled frees the license,
cross-tenant isolation), new `LiveClasses/BatchMentoringSessionTest` (mentoring
kind + host scheduling, weekly series, teaching board, start rights incl.
non-host refusal, student 403, cross-tenant 404). `composer lint`, `npm run
typecheck`, `npm run build` pass.
