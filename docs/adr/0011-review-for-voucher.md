# ADR 0011 — P2.6 Review-for-Voucher engine: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §5 Stage 3 + §6.8, BUILD-PLAYBOOK P2.6 (the last Phase-2 feature, deferred by ADR 0010)

## Context

After a bootcamp completes, the platform auto-requests a testimonial (stars +
text + optional video + consent-to-publish) via a one-tap magic link. A
**verified** (admin-approved) submission both publishes to the public /reviews
wall **and** auto-issues an admin-configured voucher that pre-applies to the
student's payment link. Founder-confirmed scope: capture includes an **optional
video upload**; issuance is **admin-verify-then-issue** (not auto-on-submit);
approved testimonials **reuse the existing `Review` wall** rather than a separate
model.

**Compliance guard (hard):** the voucher is tied to the platform testimonial,
never a Google review — Google prohibits incentivized reviews. The Google ask
stays separate and unconditioned. This lives in the request-message, the
submission-form, and the admin-moderation UI copy.

## Decisions

1. **Two voucher tables.** `Voucher` is the admin-CRUD *definition/campaign*
   (type flat ₹ / percent, value, expiry, course scope, usage + per-user limits,
   stacking, an `is_review_reward` flag marking THE voucher auto-issued on
   approval, `active`). `VoucherIssue` is the per-student *issued instance*
   (unique code, status, computed `discount_paise`, `expires_at`, links to the
   testimonial + eventual fee plan). This satisfies both "voucher manager
   (create/edit vouchers)" §6.8 and "voucher auto-issued per student" §5, and
   gives redemption analytics one row per issuance.
2. **Verify-then-issue.** `Testimonial` lands `pending`. `ApproveTestimonial` is
   the single gate that (a) writes a `platform` `Review` (`is_active=true`) so it
   flows into the existing `/reviews` wall with **zero frontend change**, (b)
   `IssueVoucher` from the tenant's active `is_review_reward` voucher, (c) audits
   `testimonial.approved` + `voucher.issued`, (d) messages the student the code.
   `RejectTestimonial` publishes nothing and issues nothing. Approval requires the
   student's `consent_publish`.
3. **Pre-apply is server-owned + admin-confirmed.** `FeePlanController@store`
   accepts an optional `voucher_code`; when present `ResolveVoucherDiscount`
   validates it for (student, batch/course) and **computes `discount_paise`
   server-side** (the client value is ignored when a voucher applies — money stays
   server-owned per CLAUDE.md). The create-plan UI surfaces the student's
   available voucher via `GET fee-plans/voucher` so the admin one-clicks it. On
   create, `ApplyVoucherToFeePlan` flips the issue to `applied`
   (`fee_plan_id`, `discount_paise`, `applied_at`). The discount still lands on
   instalment 1 through the existing `PreviewSchedule` path — so the payment link
   (auto-delivered since P2.5) carries the reduced amount.
4. **Money math is integer paise.** A flat voucher's `value` is paise; a percent
   voucher's `value` is basis points → `intdiv(total * bps, 10000)`, capped at
   `max_discount_paise` and never exceeding the total.
5. **Stacking is honestly scoped.** `allow_stacking=false` (default) enforces
   **one voucher per fee plan**. True multi-voucher stacking math is **deferred**
   — the field is stored and surfaced now, not silently dropped.
6. **New permission `manage-vouchers`** gates the voucher manager **and**
   testimonial moderation (approval issues a voucher — one responsibility).
   Granted to `admin` (super-admin bypasses). The RolePermission count moved
   14 → 15.
7. **The trigger is `CompleteBootcamp`, via an event.** There is no "final
   session" concept in the schema (`LiveSession` has no finality flag, `Batch` has
   no `liveSessions()` relation), so completion is the only signal.
   `CompleteBootcamp` dispatches `BootcampCompleted`; the auto-discovered
   `RequestTestimonials` queued listener sends each attendee a `review_request`
   magic link. Idempotent — a student who already has a testimonial for that batch
   is skipped, so a re-completed bootcamp does not double-send.
8. **Circular-FK avoidance.** `voucher_issues.testimonial_id` is a real nullable
   FK; `testimonials.voucher_issue_id` is an indexed nullable column with **no DB
   FK** (breaks the cycle; all three tables are fresh creates, so SQLite is fine).
9. **Video upload is the first binary→S3 seam.** Testimonial videos store to
   `Storage::disk('s3')` under `testimonials/{tenant_id}/…` (the
   `recordings/`/`receipts/` convention). `TestimonialResource` mints a temporary
   signed URL, falling back to null when the driver cannot (tests use
   `Storage::fake('s3')`).

## Consequences

- End-to-end: bootcamp completes → each attendee gets a testimonial request →
  student submits (pending) → admin approves → review published + voucher issued
  → admin raises a fee plan that pre-applies the voucher → reduced payment link
  auto-sends. Verified live on a seeded scratch DB.
- The student `me/testimonials` + `me/vouchers` routes run under `auth:sanctum`
  **without** `tenant.user`, so `TenantContext` is unset there; every new Action
  wraps its body in `TenantContext::run($tenant, …)` (the `SendLeadWelcomeMessage`
  pattern) so scoping and `tenant_id` auto-stamping are always correct.
- New scheduled work: none. The listener rides the existing sync/queue path.
- **Deferred (owner-visible):** true multi-voucher stacking math; testimonial
  video transcoding/thumbnails; the self-paced/monetization-settings reuse of this
  admin-settings pattern (**P2.8**). Next: **P2.7 — Student Support Desk**.
  Standing ops note: production still needs cron for `fees:run-ladder` +
  `conversions:run-nudges`.
