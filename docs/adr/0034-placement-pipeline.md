# ADR 0034 — P4.5b Placement pipeline

- **Status:** Accepted
- **Date:** 2026-07-18
- **Context:** PRD §6.11 placement — second half of P4.5. Completes the
  progression ladder: AI mock → human mock → placement pool → placed.

## Decisions

**The pool gate is one class, used by the checklist AND the API.**
`PlacementPool` checks three bars — PRI ≥ `placement.pool.min_pri`, an
APPROVED CV (ADR 0033), and a completed placement interview
(`MentorSession`, ADR 0032) with mentor feedback ≥ the bar. The student
checklist renders these checks and `ApplyToJob` re-runs them server-side —
the UI can never promise what the API refuses.

**The job board is curated, never scraped.** `job_postings` are entered by
the placement team (real openings only), scoped per course (null = all),
with close dates. Applying attaches the student's latest APPROVED CV — the
exact document the recruiter receives — one application per posting.

**Every real round feeds the flywheel.** `job_application_rounds` capture
type/date/outcome/debrief per round. When the student consents
(`share_debrief`), the debrief pipes into the P4.2 transcript pipeline
(source `debrief`, role from the posting) — parse → anonymise → review
queue — so the interview bank sharpens with every real interview.
Unshared debriefs stay private to the student.

**Marking PLACED has exactly four side effects, all human-reviewable.**
Timestamp + offer details; a consent-gated celebration DRAFT (named or
anonymous per the consent captured at apply time; staff publish via the
P3.7 flow — never auto-broadcast; no consent → nothing); a congratulations
card for the student; and the pay-after-placement fee milestone raised as
an audit entry + officer notification — money movement stays a HUMAN step
per PRD §7 until the founder sets the automatic-instalment policy.
Re-marking placed is idempotent.

**The Proof Engine is aggregates + disclaimer, nothing else.** Public
`/v1/proof` returns placed/offer/company counts and a 90-day window from
real records only, with the stored never-claims disclaimer in every
response. No names, no salaries, no per-student data (DPDP).

## Consequences

- 11 Pest tests in `tests/Feature/Placement/`: checklist assembly and
  flip, gate refusal, apply (CV attach, consent, duplicate block), board
  scoping, shared-debrief → bank (parsed to a pending question), private
  debrief, withdraw rules, officer gating + job CRUD, the full placed
  side-effect set (idempotent), consent-off celebration skip, and proof
  aggregates + disclaimer (suite: 628).
- Deferred, flagged for founder policy: automatic pay-after-placement
  instalment creation (today: audited officer task), and the end-of-course
  comprehensive report (the P3.5a reports engine is the natural home).
- Local dev note: `PLACEMENT_MIN_PRI=0` is set in the local `.env` so
  demos clear the pool gate; production uses the default 70.
