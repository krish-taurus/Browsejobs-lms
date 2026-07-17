# ADR 0037 — Candidates directory & batch reallocation

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.13 ops (batch/roster management)
- **Relates to:** P1.4 roster actions (AddStudentToBatch, TransferBatchMember, RemoveBatchMember), ADR 0036 (class scheduling — same batch admin surface)

## Context

Until now students could only be seen batch-first: open a batch, look at its roster. There
was no institute-wide student list and no way to filter students by batch or reallocate
them from a student-centric view. The founder asked for exactly that — an all-candidates
view filterable by batch, plus the ability to move a student between batches or add them to
additional batches.

The roster engine already supported the moves; what was missing was the student-first
doorway.

## Decision

1. **A student-first `GET admin/students`** (gated `can:manage-rosters`): every
   `user_type=student` in the tenant, each with their *current* batch memberships (the
   `member_id` included so the UI can act on a specific membership), filterable by
   `batch_id` and searchable by name/phone/email, paginated. Transferred and dropped
   memberships are omitted from the batch chips — they are history, not a batch the student
   is in.

2. **"Add to an additional batch" reuses `AddStudentToBatch` unchanged.** The schema already
   allows a student in multiple batches (unique on `batch_id+user_id`, not per student), and
   `AddStudentToBatch` already blocks a duplicate in the *same* batch, capacity-guards, and
   audits. So `POST admin/students/{student}/enrollments` is a thin doorway, not new
   business logic. This is deliberately distinct from **moving** a student, which is the
   existing `members/{member}/transfer` (source → `transferred`, new membership in target).

3. **Reallocation actions are reused, not duplicated.** The candidates page calls the
   existing member-scoped `transfer` and `remove` routes with the `member_id` from the list.
   No second copy of transfer/remove logic exists.

4. **Staff are excluded and the tenant scope holds.** The list is `user_type=student` only
   (the admin never lists themselves), and every query rides the tenant global scope, so no
   cross-tenant student or batch is ever reachable.

## Consequences

- **Positive:** One screen answers "who is this student and which batches are they in", and
  reallocation (add / move / remove) happens from there without hunting through batch pages.
  Multi-batch enrolment — always allowed by the schema — is now actually usable.
- **Trade-offs:** Move and remove still use `window.prompt` for the target/reason, matching
  the existing batch-page pattern; a richer picker is a later polish. The list paginates at
  30 but the page does not yet render pager controls (search/filter narrow it in practice) —
  a follow-up if tenants grow large.
- **Deferred:** bulk reallocation (move many at once); a pager UI; showing fee/attendance
  context inline (the data exists via the coach/fee endpoints).

## Verification

`php artisan test` — full suite **661 passing** (649 + 12), incl.
`Feature/Admin/StudentDirectoryTest`: lists all students; shows current batches with
`member_id`; filters by batch; searches by name/phone; **adds to an additional batch keeping
the current one**; rejects a duplicate-in-same-batch; enforces target capacity; a move drops
the source and shows only the target; excludes staff; denies without `manage-rosters`; and
**cross-tenant denial** on both list and enroll. Pint clean. `npm run typecheck`, `lint`,
`build` pass. The candidates page (search + batch filter + add/move/remove) is wired to the
new endpoints and reachable from the admin nav.
