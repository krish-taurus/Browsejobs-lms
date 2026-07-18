# ADR 0043 — P4.6b Career+ job-probability boosters

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.20 (job-probability boosters, Career+)
- **Relates to:** ADR 0035 (P4.6a retention — the first half of P4.6), ADR 0033 (CV generator pattern), ADR 0030 (real-interview bank), ADR 0034 (placement / job_applications), P2.8 (entitlements / Career+ subscription)

## Context

P4.6 was split: P4.6a shipped review-protection & retention (ADR 0035). This is **P4.6b —
the job-probability boosters**: LinkedIn optimiser, GitHub portfolio builder, interview-day
prep packs, and per-application CV tailoring, all gated behind the Career+ subscription. Every
reuse point already existed on main (`CvProfileData`, `RealInterviewQuestion`, `job_applications`,
the `career_plus` entitlement), so this slice is additive.

## Decisions

1. **Career+ is a route middleware.** `EnsureCareerPlus` (alias `career-plus`) checks
   `EntitlementService::hasEntitlement($user, 'career_plus')` and 403s otherwise. The booster
   *status* endpoint (`GET me/boosters`) is open so the client can show the upsell and any
   previously generated output; only the *generate* endpoints and CV tailoring sit behind the gate.

2. **The boosters follow the CV generator's fail-safe pattern exactly.** Each rephrases the
   student's REAL facts and, on any AI/budget/transport failure or malformed JSON, degrades to a
   deterministic assembly of the same facts — a Career+ member always gets output, never an error.
   Every output is brand-scrubbed (`str_ireplace` on "browsejobs") like the CV, so a portfolio or
   LinkedIn profile never reveals the bootcamp. New `AiPurpose` cases: `LinkedinOptimize`,
   `GithubPortfolio`, `InterviewPrep`.

3. **Nothing is invented — this is the compliance boundary.**
   - LinkedIn optimiser rephrases `CvProfileData` facts (verified modules, projects, mock
     strengths, the student's own profile) — never a new employer or title.
   - GitHub builder describes the student's **own passed lab code** (added a `source`-and-`lesson`
     query; also fixed a latent missing `CodeSubmission::lesson()` relation `CvProfileData` relied on).
   - Prep packs pull only **approved** `RealInterviewQuestion` rows for the student's role/course
     (reusing the GapReport role/course filter); the AI only groups and prioritises real questions,
     and the pack is honestly empty when the bank has no coverage.

4. **Latest-per-kind storage.** `career_boosters` keeps one row per `(user, kind)` with the JSON
   content and `content_source` (ai|fallback), so a student revisits without re-spending tokens;
   regenerating overwrites.

5. **CV tailoring reuses `GenerateCv`.** `POST me/applications/{application}/tailor` generates a
   `SOURCE_TAILORED` CV from the job posting's description and links it to the application. It
   spends **no CV credit** — CV refresh is part of the Career+ subscription — and the Career+ gate
   is the payment boundary.

## Consequences

- **Positive:** the four boosters ship gated behind the existing Career+ product with no new
  billing surface; each degrades gracefully and never fabricates. The `CodeSubmission::lesson()`
  fix also hardens `CvProfileData`.
- **Trade-offs:** the GitHub builder produces README *copy*, not an actual pushed repo (out of
  scope — no GitHub OAuth). Prep-pack quality tracks the interview-bank's coverage for the role.
  LinkedIn/GitHub output is text to paste, not an API-driven profile update.
- **Deferred:** GitHub OAuth to push a real repo; LinkedIn API integration; per-application prep
  packs (today the pack is role-level).

## Verification

`php artisan test` — full suite **710 passing** (699 + 11), incl.
`Feature/Boosters/CareerBoostersTest`: status shows career_plus false + no boosters pre-sub;
**every generate endpoint and tailoring is gated (403 without Career+)**; LinkedIn optimises from
facts on a JSON reply and **falls back deterministically on garbage**; GitHub builds from the
student's passed labs; the prep pack pulls **approved bank questions for the role** and is
**honestly empty with no coverage**; tailoring **produces and links a JD-tailored CV**, is denied
without Career+, and **404s another student's application**; auth required. Pint clean. `npm run
typecheck`, `lint`, `build` pass; fresh `migrate --seed` applies the new table. The CV page carries
a Career+ boosters section (upsell + generate + results); the placement page has a per-application
"Tailor CV (Career+)" action.

**P4.6 is now complete** (P4.6a retention + P4.6b boosters).
