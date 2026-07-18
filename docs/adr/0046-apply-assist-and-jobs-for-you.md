# ADR 0046 — "Jobs for You" relevance + Apply Assist (P4.8b/c)

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.22 (Live Job Feed & Apply Assist)
- **Relates to:** ADR 0045 (job-feed sources & ingestion), ADR 0033 (CV generator reused for tailoring),
  ADR 0034 (placement tracker extended here)

## Context

P4.8a ingested a live, skill-extracted job feed. This closes §6.22: rank the feed per student, let
them apply in one tap with a tailored CV, and track the application — without ever auto-submitting.

## Decisions

### Relevance ("Jobs for You", P4.8b)
`RelevanceScorer` scores each item against the student's own skills (their CV-profile skills + the
modules they've studied) and role fit, returning a **match %** plus the **why**: matched skills vs. the
gap. `JobsForYou` ranks active, unexpired, non-dismissed items saved-first, then by match, source
priority, and freshness. Save/dismiss lives in `job_feed_saves` (one row per student+item). The `me/`
route group carries no tenant context, so every query scopes to the viewer's tenant explicitly, and
save/dismiss/apply 404 a cross-tenant item. A daily `jobs:nudge` messages students about new strong
matches.

### Apply Assist (P4.8c)
`POST me/jobs/{item}/apply`:
1. Tailors the student's CV to THIS JD via `GenerateCv` (`SOURCE_TAILORED`) — **free**, because
   applying is the outcome we want (standalone §6.7 generations still cost credits).
2. Logs a `JobApplication` in the tracker and marks the feed item `applied`. Idempotent per
   student+item.
3. Returns the deep link; the UI opens the source posting.

**Tracker extension:** `job_applications.job_posting_id` became nullable and a nullable
`job_feed_item_id` was added, so one tracker holds both internal-posting and external-feed
applications (the placement page falls back to the feed item's title/company when there's no posting).
Interview rounds/outcomes already capture back into the Advice Graph. A daily
`applications:follow-ups` command nudges once (a 5–6-day age window) on applications with no round yet.

### Apply Copilot — feature-flagged stub (explicitly not built)
`POST me/jobs/{item}/copilot` is gated by `config('features.apply_copilot')` (off by default). It is the
Phase-5 extension point for human-in-the-loop ATS pre-fill; enabled, it currently returns `501`. **Fully
autonomous auto-apply stays deferred** (PRD §6.22 — portal ToS put ban risk on the *student*); the
Copilot + tracker architecture is built to extend into it the day an official apply API makes it safe.

## Consequences

- **Positive:** one-tap apply produces a JD-tailored CV, opens the real posting, and auto-tracks the
  outcome — internal and external roles share one tracker and one feed. Relevance explains itself
  (match + gap), which doubles as coaching.
- **Trade-offs:** relevance is skill-overlap + role fit (no per-student location field yet). Apply
  Copilot is a stub; nothing submits on a student's behalf. Follow-up nudges are best-effort (no
  template configured yet → no send).

## Verification

`Feature/JobFeed/JobsForYouTest` + `ApplyAssistTest`: relevance match/gap; saved-pin/dismiss-drop;
expiry exclusion; cross-tenant isolation; apply tailors a free CV + logs + deep-links + marks applied;
duplicate-apply 422; expired/cross-tenant 404; Copilot flag gate (403 off, 501 on). Part of P4.8 — full
suite **768 passing**; Pint clean; web typecheck/lint/build pass; the nullable-column migration applies
on fresh migrate.

**P4.8 (Live Job Feed & Apply Assist) is complete.**
