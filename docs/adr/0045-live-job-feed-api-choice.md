# ADR 0045 — Live Job Feed sources & licensed-API choice (P4.8a)

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.22 (Live Job Feed & Apply Assist)
- **Relates to:** ADR 0044 (Curriculum Intelligence / JD skill-extraction pipeline it reuses),
  ADR 0034 (placement / job_postings the internal adapter reads)

## Context

The live job feed aggregates open roles from multiple sources onto every student's dashboard. The PRD
requires **pluggable, compliant sources — never raw portal scraping** — and, at build time, choosing
**one** licensed job API by Indian-IT coverage and cost (Adzuna / JSearch / Jooble).

## Decisions

### One adapter interface
Every source implements `FeedAdapter` (the PRD's "`JobFeedSource` interface"): `kind()` + `pull()
: list<NormalizedJob>`. `FeedRegistry` resolves a source's `kind` to its adapter. Adapters never throw
on empty/unreachable feeds — they return `[]`, so a source outage degrades to "no new items." Concrete
adapters shipped: **internal postings** (reads `job_postings`) and a **licensed API** adapter.
Partner and ATS (Greenhouse/Lever) sources are the same pattern and register here as they land;
**CSV/manual** is pushed through the admin importer, not pulled.

### Licensed API: JSearch (via RapidAPI)
Chosen over Adzuna and Jooble for the Indian-IT use case:
- **JSearch** aggregates Google-for-Jobs results (LinkedIn, Indeed, Naukri postings surface here
  **legitimately, via the API** — not scraped), giving the broadest Indian-IT coverage of the three,
  with role/location/seniority filters that map cleanly onto `NormalizedJob`. RapidAPI free tier
  covers early volume; paid tiers scale per request.
- **Adzuna** has a clean official API and good analytics but thinner India-IT depth and requires
  per-country app credentials.
- **Jooble** has partner-gated access and less predictable India coverage.

The API is reached through a swappable `JobApiTransport` so the ingestion pipeline never depends on
the vendor's response shape. Until a key is provisioned, `NullJobApiTransport` is bound (a safe no-op);
the real JSearch transport slots into the same binding in `AppServiceProvider`, and tests bind a fake
(no external call ever happens — CLAUDE.md).

### Ingestion reuses the §6.21 JD pipeline
`IngestJobFeed` normalises, **dedupes on a fingerprint** (external id when present, else
company+title+location), **quality-filters** thin postings, **freshness-stamps** an `expires_at`, and
queues `ExtractJobFeedItemSkills` — which reuses the `jd_extract` prompt with the same AI→keyword
fail-safe. The twice-daily `feed:sync` command pulls active sources and expires stale items.

## Consequences

- **Positive:** new sources plug in behind one interface; the vendor is swappable and absent from the
  pipeline; no scraping, no ToS/IP/legal risk. Internal openings and market roles share one feed.
- **Trade-offs:** JSearch depth depends on RapidAPI tier; the real transport is deferred until a key is
  provisioned (Null transport keeps the API source a safe no-op meanwhile). Partner/ATS adapters are
  interface-ready but not yet implemented.
- **Explicitly out of scope:** raw scraping of LinkedIn/Naukri, and fully autonomous auto-apply
  (PRD §6.22 — ban risk lands on the student). Apply Copilot stays human-in-the-loop (P4.8c).

## Verification

`Feature/JobFeed/JobFeedIngestionTest`: internal-postings ingest + skill extraction; dedupe across
syncs; licensed-API pull through a **fake transport**; low-quality skip; CSV import; placement-ability
gate; cross-tenant isolation. Part of P4.8a — full suite green.
