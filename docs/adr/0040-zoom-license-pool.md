# ADR 0040 — Zoom license pool, per-mentor allocation & auto-record

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.3 (live classes, Zoom)
- **Relates to:** ADR 0036 (admin class scheduling), ADR 0039 (Zoom credentials in settings), P1.5 (Zoom integration)

## Context

Zoom hosted every meeting under the account's own user (`/users/me/meetings`), so two
classes could not run at the same time — one Zoom user can host only one live meeting.
The founder needs concurrent classes, which means several licensed hosts, each allocated
to a mentor. Separately, classes were not being recorded: the app never asked Zoom to
record, relying on whatever the account default was.

## Decision

1. **A `zoom_licenses` pool.** Each row is a Zoom host (a `zoom_user_id` — the user/email
   meetings are created under) with a label, optionally allocated to one mentor
   (`mentor_id`, unique per tenant so a mentor holds at most one). Tenant-scoped, since
   mentors are tenant users; the underlying Zoom account credentials stay platform-global
   (ADR 0039).

2. **A batch's classes host under its trainer's license.** `CreateZoomMeeting` resolves
   `batch.trainer_id → the mentor's active license → its zoom_user_id` and creates the
   meeting under that Zoom user. No license (or no trainer) falls back to `me`, exactly as
   before — so nothing breaks for batches without an allocation. Two mentors with their own
   licenses can now teach simultaneously.

3. **Auto-record per session, default on.** `live_sessions.auto_record` (default true) is
   passed to Zoom as `auto_recording: cloud`; unchecking "record" at schedule time sends
   `none`. The scheduler UI carries the toggle. Stopping a recording *mid-class* remains a
   host action inside Zoom — the API sets the meeting's default, not a live control.

4. **The Zoom client signature is extended, not forked.** `createMeeting` gains optional
   `hostUserId` and `autoRecord` params. Both default to the old behaviour
   (`me` / no `auto_recording` key sent), so the 1:1 mentoring flow (`SyncMentorZoom`),
   which shares the client, is untouched — only the batch-class job passes the new values.

5. **The pool is admin-managed** (`can:manage-batches`): add/allocate/remove licenses, with
   an inline mentor picker drawn from trainers and mentors. A mentor-already-has-a-license
   clash returns a clean 422 rather than a raw DB error.

## Consequences

- **Positive:** Concurrent classes work by allocating a license per mentor; classes record
  by default with an explicit opt-out. Adding capacity is adding a license row.
- **Trade-offs:** A "license" is a Zoom *user* under one S2S account, so the account must
  actually have that many licensed users — the app models the allocation, Zoom enforces the
  seat. Mid-class stop-recording is out of scope (host control in Zoom). One license per
  mentor; a mentor teaching two overlapping batches would still clash (rare; a scheduling
  concern, not a licensing one).
- **Deferred:** auto-allocating a free license when scheduling collides; surfacing which
  license a session actually used; applying the pool to 1:1 mentor sessions.

## Verification

`php artisan test` — full suite **679 passing** (670 + 9), incl.
`Feature/Admin/ZoomLicenseTest`: pool CRUD with mentor allocation; a mentor **can't hold two
licenses**; a class **is created under the batch trainer's allocated Zoom user with cloud
recording on**; **falls back to the default host** when the trainer has no license; a session
**scheduled with recording off** sends no-record; recording **defaults on**; `manage-batches`
required; cross-tenant 404. Pint clean. `npm run typecheck`, `lint`, `build` pass; fresh
`migrate --seed` applies the new table and the `auto_record` column. The admin Zoom-licenses
page (add / allocate-per-mentor / remove) and the "Record this class" toggle in the scheduler
are wired.
