# ADR 0048 — Scraper job sources, public board, and per-JD interview prep

- **Status:** Accepted
- **Date:** 2026-07-23
- **PRD:** §6.22 (Live Job Feed & Apply Assist)
- **Relates to:** ADR 0045 (feed sources & JSearch), ADR 0046 (Jobs for You + Apply
  Assist), ADR 0029 (mock interviewer), ADR 0033 (CV generator)
- **Context:** Founder decisions (2026-07-23): scrape Naukri + LinkedIn via Apify
  actors for the freshest coverage — an **explicit founder override of the PRD's
  "no raw portal scraping" rule**, made with the ToS risk on record; show a
  public job board of the last 7 days refreshed every morning; per-student
  match % **and** an interview-confidence score; per-JD likely questions and a
  quick JD-scoped mock; JD-tailored CV on Apply (already shipped, ADR 0046);
  **no auto-apply, ever**. Open registration so non-students can prepare too.

## Decisions

**Scraping is a source kind, not a rewrite.** `JobFeedSource` gains
`kind=scraper`; `ScraperAdapter` implements the existing `FeedAdapter`
interface, so ingestion, dedupe, quality filtering, skill extraction, expiry,
"Jobs for You", and the admin screen are all untouched. The adapter delegates
to a swappable `ApifyTransport` (`run-sync-get-dataset-items`; Null when no
`APIFY_TOKEN`, fake in tests — no external call in CI). A source's config
carries the actor id and the actor's input document verbatim, so switching
actors or search terms is admin configuration. Field mapping is
alias-tolerant because every actor names fields differently. Scraper sources
default `freshness_days` to **7** (the founder's rolling window); the existing
`feed:sync` schedule (06:00 & 18:00 IST) is the "refreshed every morning".
JSearch (ADR 0045) remains available beside it.

**The public board is a teaser, not a link farm.** `GET /api/v1/jobs` (tenant
by domain, unauthenticated, throttled) returns the last 7 days of active
items with truncated summaries, skills, and up to three cached prep questions —
**no apply URLs**. The Next.js `/jobs` page renders it with ISR (30 min) +
JobPosting JSON-LD for SEO, and every card funnels to registration ("see my
match & prepare") or the free masterclass, per the funnel-spine rule.

**Confidence is a second, honest number.** `ConfidenceScorer` blends the item's
match % (0.5) with the student's cached nightly PRI (0.3) and best completed
mock score (0.2), renormalising over whichever signals exist and reporting
`based_on` so the UI says what the number is built on — and prompts a mock
when none informs it. No fabricated precision; no ScoreCalculator on hot paths.

**Per-JD prep is generated once per posting and cached.** `GET
me/jobs/{item}/prep` returns approved real-interview-bank questions matching
the role (labelled "asked in a real interview" — never invented) followed by
JD-derived AI questions (`job_questions.v1` prompt, strict JSON, existing
AiGateway budget/logging), with a deterministic skills-based fallback when AI
is unavailable. Cached on `job_feed_items.prep_questions` so cost scales with
postings, not viewers; the public board teases the first three only when
already generated (no anonymous AI spend).

**The quick JD mock is a hidden blueprint.** `POST me/jobs/{item}/mock` spins a
text mock through the existing engine via a per-posting `MockBlueprint`
(`job_feed_item_id`, `is_active=false` so course pickers never list it,
`course_id` now nullable for open-registration job-seekers). Role +
competencies come from the posting, so the interviewer stays on that JD;
scorecards and the PRI blend are unchanged.

**Monetisation lands on existing rails, later.** Registration is already open
(`auth/register`); Apply Assist's JD-tailored CV stays free per the PRD;
Career+ (₹499/mo) remains the unlimited tier for boosters/tailoring. Metered
free allowances and jd-mock/jd-cv packs need founder-approved prices before
seeding — deferred to a follow-up, tracked with the revenue-model proposal.

## Consequences

- **Positive:** freshest possible board with zero new pipeline; public SEO
  surface that feeds the funnel; prep + confidence give students a reason to
  log in daily; all AI cost is bounded per posting.
- **Trade-offs / risks:** scraping LinkedIn violates its ToS and Naukri's
  terms — accepted explicitly by the founder; Apify actor output shapes can
  drift (alias-tolerant mapping + skipped rows degrade gracefully, and a dead
  actor syncs to nothing rather than erroring). Confidence is a heuristic
  blend, clearly labelled with its basis.
- **Deferred:** paid packs + monthly caps (founder pricing), JD-specific mock
  question injection into the interviewer prompt, per-job mock deep link with
  the blueprint pre-armed on the /mock page, Apply Copilot (Phase 5 flag).

## Verification

Pest: `JobFeed/ScraperFeedTest` (adapter mapping incl. messy actor fields,
7-day expiry default, dedupe, null-transport no-op, tenant isolation),
`JobFeed/PublicJobBoardTest` (7-day window, expired/old exclusion, no auth,
teaser only when cached, cross-tenant isolation by domain),
`JobFeed/JobPrepTest` (real-bank + fallback questions, caching, quick mock
creates a hidden JD blueprint + resumes, text-practice gate, cross-tenant
404s). Full suite green; Pint, typecheck, lint, `next build` clean.
