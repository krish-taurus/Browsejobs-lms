# ADR 0044 — P4.7 Curriculum Intelligence & the Advice Graph

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.21 (Curriculum Intelligence & the Advice Graph)
- **Relates to:** ADR 0030 (real-interview bank), ADR 0033 (CV generator fail-safe pattern),
  ADR 0034 (placement pipeline), ADR 0015 (coach-panel scoring / PRI), ADR 0021 (reports & digests),
  ADR 0038 (nav grouping)

## Context

P4.7 builds the platform's data flywheel: every placement-adjacent signal feeds ONE intelligence
layer that powers syllabus recommendations, evidence-backed coaching, and PRI calibration. Shipped in
four slices, all additive over existing tables (real-interview bank, placement, scoring).

## Decisions

### P4.7a — Job-market JD ingestion → demand trends
`market_jds` holds JDs imported by the placement team (manual or CSV) — **never scraped**, deduped on a
role+body fingerprint. A queued, idempotent `ExtractJdSkills` job (prompt `jd_extract` v1) parses each
into a canonical skill list; on any AI/budget failure or malformed JSON it degrades to a deterministic
keyword scan, so a JD always contributes a signal. `SkillDemand` aggregates parsed skills into
anonymised per-course demand trends. Gated by `can:manage-placements`.

### P4.7b — Syllabus Recommendation Reports
`CurriculumEvidence` fuses four signals for a course — job-market demand, real-interview-bank topic
frequency (weighted by `asked_count`), candidate failure points (struggle/rejection topics), and
current outline coverage — into one evidence bundle. `GenerateSyllabusRecommendation` asks the model
(`syllabus_recommend` v1) for add/expand/deprioritise items and, on failure, degrades to a
deterministic gap analysis. **It never invents a statistic** and stores the evidence alongside the
report. Trainer/admin approve or reject (`can:manage-curriculum`); approval records the decision,
audits it, and fires the idempotent `CurriculumChanged` event so the syllabus regenerates once edits
are applied — running batches unaffected. Quarterly `curriculum:recommend` command.

### P4.7c — Advice-graph inputs
- **Hiring-partner feedback**: an officer requests feedback for an application; the company submits via
  an unguessable public token (companies aren't platform users — same pattern as shared CVs),
  single-use, candidate never named beyond the role. The `gaps` field is the sharpest signal.
- **Salary benchmarks**: a curated role/city/experience CTC-percentile dataset; figures entered as LPA,
  **stored in paise** (never floats). Students read benchmarks by role for offer evaluation.
- **Alumni check-ins**: a daily idempotent `alumni:checkins` command schedules 6- and 12-month
  post-placement check-ins from each placement's `placed_at`, best-effort notifies the alumnus, who
  responds in-portal (still employed, current CTC, would-refer).

### P4.7d — PRI calibration + evidence-backed coaching (privacy-first)
- **PRI weight calibration**: `CalibratePri` measures the point-biserial correlation of each PRI signal
  (mastery, engagement, attendance) with actually getting placed, across the tenant's outcome cohort,
  and sets each weight ∝ its positive correlation. `ScoreCalculator` prefers the latest applied
  `PriCalibration` over config defaults. **Guardrails**: below 8 students, or with fewer than 3 in the
  placed/not-placed group, or when no signal correlates positively, it does not calibrate — config
  defaults stand. Monthly `pri:calibrate` command.
- **Evidence-backed coach claims**: `AdviceGraph::insightsFor()` turns anonymised cohort aggregates
  into claims like "students who practised 3+ mock interviews were placed 2.1× as often," surfaced on
  the coach panel.

**Two privacy invariants, enforced in `AdviceGraph` and asserted by the mandatory
`AggregationPrivacyTest`:**
1. **k-anonymity** — a claim is suppressed unless BOTH compared groups have ≥ `MIN_COHORT` (5)
   students, so no individual is re-identifiable.
2. **No per-student leakage** — claims are aggregate numbers only; never another student's name, id, or
   record. Tenant isolation comes from the global scope active in the request context.

## Consequences

- **Positive:** the syllabus, the coach, and PRI all now track real placement outcomes with evidence,
  not opinion. Every AI surface degrades safely; every cross-student surface is k-anonymised and
  tenant-scoped.
- **Trade-offs:** demand trends and recommendations are only as good as the JD/interview-bank coverage
  the team imports. Calibration holds until a cohort is large enough — small tenants keep config
  defaults, by design. Attendance calibration uses live per-cohort averages; mastery/engagement come
  from the cached `StudentScore`.
- **Deferred (P4.8):** the Live Job Feed + Apply Assist consume this same JD pipeline.

## Verification

`php artisan test` — full suite **749 passing** (from 710 at P4.6b), across
`Feature/Market/*` and `Feature/Advice/*`, including: JD extraction fail-safe + dedupe + demand
trends; syllabus recommendation AI + deterministic fallback + approval/curriculum-change; public
partner-feedback token single-use; salary paise conversion; alumni check-in idempotency; PRI
calibration math + cohort guardrails; and the **aggregation-privacy test** (k-anonymity suppression,
no individual leakage, cross-tenant isolation, coach-panel integration). Pint clean; web
typecheck/lint/build pass; fresh `migrate --seed` applies all new tables. Admin surfaces: "Market
intel", "Syllabus advisor", "Advice graph"; student surfaces: alumni check-in card, salary-benchmark
lookup, coach-panel insights.

**P4.7 is complete.**
