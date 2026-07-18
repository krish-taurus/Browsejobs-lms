# ADR 0047 — DPDP data-access & deletion workflows (P4.9b)

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §8 (DPDP Act 2023 — access/deletion workflows) / §10 (launch blocker)
- **Relates to:** ADR 0039 (super-admin / can:manage-settings gate reused for the grievance officer)

## Context

The DPDP Act 2023 gives data subjects the right to access and to erasure. The PRD marks the
access/deletion workflows a **launch blocker**. We need a real request → review → fulfil flow, not a
mailto.

## Decisions

- **`data_requests`** captures a student's `access` or `deletion` request (pending → completed /
  rejected). A student raises one from their profile (rate-limited, one open request per type) and,
  for a completed access request, downloads the compiled export. A staff **grievance officer**
  (gated by `can:manage-settings` — the sensitive super-admin gate) processes or rejects each; every
  action is audit-logged.

- **Access = export the subject's own data.** `DataExporter` compiles the student's profile, CV
  profile, enrolments, applications, payment instalments, and activity counts — reading **only that
  student's records**. The payload is stored on the request and downloaded as JSON.

- **Deletion = anonymise, not hard-delete.** `AnonymizeStudent` scrubs PII (name → "Deleted user
  {id}", email/phone → null, password/2FA cleared, `anonymized_at` stamped) and empties the
  student-authored CV profile, **while retaining transactional records** (payments, invoices, audit
  trail) the business is legally required to keep. This is the standard DPDP-compliant posture: the
  person is no longer identifiable, but financial/compliance obligations are met. Cleared credentials
  mean no further login. Idempotent.

## Consequences

- **Positive:** the launch-blocking DPDP right-to-access and right-to-erasure are satisfied with an
  auditable officer-reviewed workflow; the export never leaks another subject's data; erasure keeps
  the books intact.
- **Trade-offs:** deletion is anonymisation (records retained), which is the correct legal posture but
  not a literal row-delete. Free-text the student wrote elsewhere (e.g. ticket bodies) is out of this
  slice's scrub scope; extend `AnonymizeStudent` as new PII-bearing surfaces are added. Processing is
  manual-by-design (a human officer must review), not automatic.

## Verification

`Feature/Dpdp/DataRequestTest`: student raises access once (duplicate 422); officer fulfils access →
export carries the subject's own data (email, CV skills) and the student downloads it; deletion →
name/email/phone scrubbed, `anonymized_at` set, CV profile emptied, **account row retained**; no
double-processing; reject with reason; super-admin gate; a student can't view another's request; auth
required. 783 tests pass; Pint clean; web typecheck/lint/build pass; migration applies on fresh
migrate.
