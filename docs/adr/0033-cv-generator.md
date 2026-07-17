# ADR 0033 — P4.5a AI CV generator + ATS suite

- **Status:** Accepted
- **Date:** 2026-07-18
- **Context:** PRD §6.7. First half of the P4.5 playbook bullet; the
  placement pipeline (pool gating, job board, applications, offers, proof
  engine) is P4.5b and builds on these documents.

## Decisions

**The candidate owns their facts; the platform owns its evidence.**
`cv_profiles` holds the candidate's own truth — imported from their
uploaded CV (txt/docx or pasted text, AI-parsed by `cv_parse.v1` under an
extraction-only rule) and hand-editable (experience, personal projects,
education, links). Generation (`cv_generate.v2`) merges both fact blocks;
custom projects and experience are first-class. Parse failures degrade to
a 422 pointing at the manual editor — never a dead end.

**The document never says where they trained.** The prompt forbids any
BrowseJobs/bootcamp/course mention, and a deterministic scrub enforces it:
brand strings are stripped from every field and education entries left
without an institution (course-as-education) are dropped. education comes
ONLY from candidate-supplied facts; platform certificates stay off the CV
because they name courses. Verified live against a model reply that
deliberately leaked the brand five ways.

**ATS armour is structural + exportable.** Standard section names, single-
keyword skills mirroring the JD's spelling, action-verb one-line bullets,
reverse-chronological experience with the candidate's dates verbatim, no
tables/columns/symbols — plus a plain-text export (`/ats-text`) that any
parser on earth reads.

**Facts in, prose out — the compliance boundary is code.** `CvProfileData`
assembles the only things a CV may say: fully-completed modules, all-tests-
passed lab submissions as projects, issued certificates, mock-interview
strengths, real contact details. The LLM (`cv_generate.v1`) rephrases those
facts and is hard-forbidden from inventing employers, experience, dates or
tools (never-claims, PRD §14.3); any AI failure degrades to a deterministic
assembly of the same facts (`content_source: fallback`) — verified live: a
student with zero completed modules gets an honest, thin CV, never a
fabricated one.

**The first CV builds itself.** `CourseCompleted` → queued
`GenerateCvOnCompletion`: credit-free auto version, celebration card, and
the tenant's `cv_free_grants` (3) land in the wallet exactly once
(ledger-checked). Idempotent per user+course.

**Generations are metered; thinking is free.** New builds and JD-tailored
versions consume one `cv` credit (empty wallet → 402 with the ₹99 3-pack —
the same one-tap pattern as voice and mentor). Manual text edits and every
ATS check are free and unlimited. A consumed credit always yields a
document — budget exhaustion falls back, never errors.

**The ATS suite is deterministic.** Parse-simulation score, format lint,
quantified-bullet %, action-verb %, and JD keyword match (top-20 keyword
extraction vs the CV text) are pure functions — recomputed on every save
and JD paste at zero AI cost. Only the *writing* costs credits.

**Approval is the placement gate; edits reset it.** Placement officers
(`can:manage-placements`) review drafts and approve; approval is
audit-logged, and any student edit drops the version back to draft — the
officer approved the previous text, not whatever came after. P4.5b's pool
gating reads `status: approved`.

**Share links are tokens, not logins.** A 48-char token mints a public
branded page (`/cv/shared/{token}`); revocation deletes the token and the
page dies instantly. Approved versions carry a "verified by BrowseJobs
placement team" badge — proof the receiving recruiter can see.

**Versioning is append-only.** Every generation is a new numbered version;
nothing is overwritten, so the officer can diff what changed and the
student can walk back a bad tailor.

## Consequences

- 15 Pest tests in `tests/Feature/Cv/`: auto-build (free, grant-once,
  celebration, idempotent), credit consumption + versioning, 402 + pack,
  JD tailoring (prompt carries JD, match report scores it), fallback from
  facts, own-CV import (text + docx, unreadable-format rejection),
  hand-edited profile facts flowing into generation, the brand scrub
  surviving a deliberately leaky model reply, plain-text ATS export, free
  edits resetting approval, deterministic ATS scoring, share
  mint/public-read/revoke, officer approval + audit, cross-student
  isolation (suite: 617).
- Rendered PDF (WeasyPrint) slots in later behind the same content JSON —
  the share page and portal render from structure, not markup.
- P4.5b consumes: `CvDocument::STATUS_APPROVED` for pool gating, share
  links for recruiter handoff, and the auto-CV moment as the placement
  funnel's entry event.
