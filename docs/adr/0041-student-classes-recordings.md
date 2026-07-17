# ADR 0041 — Student live-classes & recordings surfaces

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.3 (live classes, join links), §6.8 (fee-gated recordings)
- **Relates to:** ADR 0036 (admin class scheduling), ADR 0040 (auto-record), P1.5/P1.6 (Zoom, JoinLiveSession)

## Context

The student `/classes` and `/recordings` pages were empty placeholder stubs — no data,
no join, no downloads — even though the scheduling engine and the gated `JoinLiveSession`
action existed. With admins now scheduling classes (ADR 0036) and recordings landing on
the batch (ADR 0040), the student side needed to actually show them: join upcoming
classes, and watch/download recordings. Founder ask 5c.

## Decision

1. **`me/classes`** lists the live sessions of every batch the student is an active member
   of, newest first, split by the client into upcoming and past. The list carries status,
   times, and whether a recording exists — **never the raw Zoom URL**.

2. **`me/classes/{session}/join`** reuses the existing `JoinLiveSession` gate: it returns
   the join URL only after enrolment, self-paced exclusion, fee status, and
   meeting-readiness all pass, otherwise a 422 with the specific reason. So the hand-out
   logic lives in one place, already tested, and the URL is minted per click rather than
   embedded in a list anyone could scrape.

3. **`me/recordings`** lists stored recordings for the student's batches;
   **`me/recordings/{recording}/download`** verifies active membership and the fee gate
   (recordings lock with the soft-block, PRD §6.8), then returns a short-lived signed URL —
   the same `temporaryUrl`-wrapped-in-try/catch pattern the syllabus download uses, so a
   disk that can't sign (local/test) degrades to null rather than erroring.

4. **Self-paced enrolments get recordings but not live classes.** `JoinLiveSession` already
   excludes self-paced from live join; the recording download deliberately does not, matching
   the PRD's self-paced entitlement.

5. **Both controllers run in the student's tenant context** (`TenantContext::run`), so the
   normal tenant scopes apply and a student can only ever see their own batches' classes and
   recordings.

## Consequences

- **Positive:** The two dead stub pages are now live — students join upcoming classes and
  watch/download past recordings, gated exactly as the PRD requires, with no raw URL exposure.
- **Trade-offs:** "Download notes and practice per class" from the ask is only partially met
  here: recordings are the downloadable class artifact, but notes and labs remain reached
  through their own Notes/Practice surfaces, because a live session links to a *topic*, not to
  the specific *lesson* a note or lab hangs off. Tightly binding a class to its notes/practice
  needs a session→lesson link that doesn't exist yet — deferred rather than faked.
- **No watch-progress / resume** yet (the old stub copy promised it); a follow-up once the
  player is chosen.
- **Deferred:** session→lesson linkage for per-class notes/practice; watch tracking into the
  engagement score; a real in-page player vs. opening the signed URL.

## Verification

`php artisan test` — full suite **690 passing** (679 + 11), incl.
`Feature/Me/MyClassesTest`: lists the student's classes newest-first; **the raw join URL is
never in the list**; join hands the URL to an enrolled student; a non-member is refused (422);
a **self-paced student is refused a live class**; recordings list; a download is **authorised
for an enrolled student**, **denied to a non-member** (404), and **allowed for a self-paced
student**; another batch's classes/recordings are invisible; auth required. Pint clean.
`npm run typecheck`, `lint`, `build` pass. The `/classes` (upcoming + Join, past + recording
link) and `/recordings` (list + watch) pages are wired to the new endpoints.
