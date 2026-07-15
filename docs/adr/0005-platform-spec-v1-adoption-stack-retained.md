# ADR 0005 — Adopt Platform Spec v1.0 (brand/content/compliance); retain Laravel + MySQL stack

- **Status:** Accepted
- **Date:** 2026-07-15
- **Context:** `docs/BrowseJobs_Platform_Spec_v1.pdf` (Master Build Specification v1.0, July 2026)

## Context

The founder supplied a new Master Build Specification covering brand, design
system, compliance, fee model, courses, marketing strategy, and a recommended
tech stack. Most of it is additive to PRD v1.4, but its **Section 5 recommends a
different stack**: a single Next.js 15 app + PostgreSQL + Prisma + Auth.js.

The platform as built (per PRD v1.4 §2, which mandated Laravel 11 to match the
existing BrowseJobs CRM) is: **Laravel 11 API + MySQL 8 + Redis/Horizon + Sanctum
SPA auth, with Next.js 15 as the frontend** — 8 merged milestones (P1.1–P1.8
first pass), 81 passing backend tests, working end-to-end auth in the browser.

The spec itself anticipates deviation: *"keep a DECISIONS.md — log every
deviation from this spec with a reason."* This ADR is that log entry.

## Decision

1. **Retain the current stack** (Laravel 11 + MySQL + Redis + Sanctum + Next.js
   15). Migrating to Postgres/Prisma/Auth.js would discard the entire working
   Phase 1 foundation for no product-visible gain; the spec's stack is a
   recommendation ("my CTO recommendation"), not a product requirement. The
   spec's *architectural intents* are honored equivalently: one design system
   across site+LMS ✓, shared DB ✓, RBAC ✓, queued jobs (Horizon≙BullMQ) ✓,
   Razorpay server-owned payments (P2.2) ✓, S3-compatible storage ✓.
2. **Adopt everything else in the spec as binding**, folded into CLAUDE.md and
   the requirements addendum (§14):
   - Design tokens: add `--ink-2 #1B2A44`, `--green-bg #E6F7EF`; semantic
     colour rules (green=free/verified only, red=refused-promise/error only,
     amber=stars/coach notes only, ONE primary blue CTA per view); radii
     (cards 14 / panels 22 / pills 999 / inputs 10), soft shadow only, reveal
     = fade + 18px rise.
   - Compliance: the never-claims list; the MANDATORY stat disclaimer stored
     once and rendered via a shared `<Disclaimer/>`; honest fee framing.
   - Fee model: ₹30,000 registration (3×₹10,000 EMI) after the free steps;
     placement fee = first 3 months' CTC in 6 EMIs with ₹30k adjusted;
     30-day money-back. All money server-owned.
   - Product: 7 programs (4 live: Data Engineering, DevOps & Cloud, Python
     Backend, Data Analytics; 3 waitlist: Agentic AI, Cyber Security,
     ServiceNow); the three-free-steps funnel; marketing site conversion
     mechanics (lead modal, UTM capture, sticky CTA).
   - Voice: positioning "This syllabus was not written. It was
     reverse-engineered." / tagline "Built from real interviews." No hype
     adjectives in copy.
3. **Role mapping:** the spec's 4 roles (student/instructor/placement/admin)
   are a subset of the PRD's 8; PRD roles stay (instructor≙trainer,
   placement≙placement-officer).

## Consequences

- No throwaway work; spec compliance lands as increments on the existing base.
- Anyone reading the spec's Section 5 must read this ADR: the stack deviation
  is deliberate and owner-visible.
- Open items needing founder input (do not invent): the exact three
  "Verify us on Naukri" checks; the precise label for the ~90% stat; real
  testimonials (6) and per-course syllabus content from the brochures; legal
  placeholders ([CIN], [GST], Grievance Officer name).
