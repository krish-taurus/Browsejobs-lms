# Why this repo exists

This is the `/for-employers` employer landing page, on a full copy of the LMS
at the point it was built. It lives here rather than in `browsejobs-lms`
because, while it was in review, `main` gained a **different** employers
landing page (PR #77, `feat/employers-landing-v2`) that occupies the same
files. Merging the two would have been a rewrite, not a merge. This repo keeps
this version intact until someone decides which parts of each survive.

The full `browsejobs-lms` history is here, so `git` can still find a merge base
when the code moves back.

## What this version adds

| Path | |
|---|---|
| `apps/web/src/app/for-employers/page.tsx` | the page, at `/for-employers` |
| `apps/web/src/components/employers/*` | 15 components |
| `apps/web/src/content/employers.ts` | all copy, sample report, pricing, proctoring |
| `apps/web/e2e/for-employers.spec.ts` | 4 Playwright tests |
| `apps/api/app/Support/Employers/CandidateProfile.php` | sample report shape |
| `apps/api/app/Models/Lead.php` | `employer` lead type |

It also carries three changes that are **not** about the employer page and are
worth taking regardless of which landing page wins:

- `apps/web/src/content/courses.ts` — DE gains a Git/GitHub Actions module,
  DevOps merges Ansible and Terraform into "IaC & Configuration Management",
  Docker Compose is gone.
- The contact number is `+91 79756 66665` / `+91 63634 02404` everywhere.

## The six files that collide with `main`

When migrating, these are the ones to look at first — both versions changed
them, and `main`'s version is the one currently shipping:

```
apps/api/app/Models/Lead.php
apps/api/tests/Feature/Leads/LeadCaptureTest.php
apps/web/src/app/v3/page.tsx
apps/web/src/components/employers/EmployerLeadForm.tsx
apps/web/src/components/landing/Footer.tsx
apps/web/src/content/employers.ts
```

`components/employers/` and `content/employers.ts` are the same names for
different things in the two versions. Renaming one side before merging will
save a lot of pain.

## One thing to fix before this ships anywhere

`apps/api/app/Support/Employers/CandidateProfile.php` sets
`'proctoring_captured' => false`. The page describes proctoring accurately, but
the sample report cannot show measured integrity data because the interview
pipeline does not persist it yet — no recording reference, no out-of-frame
time, no focus loss, no pasted answers, no per-answer timing. Wiring that
through `employer_interviews` is the gap between what the page says and what
the product can prove.
