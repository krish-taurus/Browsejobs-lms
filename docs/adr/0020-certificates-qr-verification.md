# ADR 0020 — P3.4c certificates + QR verification: design decisions

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD v1.4 §6.5 + CLAUDE.md. The final deferred piece of P3.4 ("Certificates auto-issued, branded, QR-verified").

## Context

When a student completes a course, auto-issue a branded certificate (rendered HTML,
mirroring the GST-receipt render seam), stored privately; the certificate carries a QR
code linking to a **public verification page** that confirms it's genuine (student name +
course + date + valid/revoked). Admins can also issue + revoke manually. The domain was
greenfield but reused the receipt renderer, the `ModuleCompleted` event wiring, and the
public-route + s3 patterns.

**Founder-confirmed scope (this session):** auto-issue on **course completion** (all
modules' topics), no pass-gating; QR → public verify page (not public file serving); admin
manual issue + revoke.

## Decisions

1. **`certificates` table** (mirrors `receipts`): tenant/user/course, a globally-unique
   unguessable `code`, a per-tenant `number`, title, issued_at, storage_path, status
   (pending|rendered), revoked_at, issued_by. `unique('code')`,
   `unique(['tenant_id','user_id','course_id'])` (one cert per student+course, so the
   auto path and a manual admin issue converge on one record).
2. **Auto-issue via a new `CourseCompleted` event.** `MarkTopicCompleted` gained a
   `fireCourseCompletedIfFinished` sibling to `fireModuleCompletedIfFinished`: on the last
   topic of a module, it batch-checks whether every topic of the course is completed (one
   `whereIn` + one count; a zero-topic course never "completes") and dispatches
   `CourseCompleted`. The queued, auto-discovered `IssueCertificateOnCompletion` listener
   calls `IssueCertificate`.
3. **`IssueCertificate` is idempotent** (existing-cert probe + the unique index; retries an
   unguessable 40-char code on collision) → creates a `pending` cert, dispatches
   `RenderCertificate`, audits `certificate.issued`, and notifies the student
   (`certificate_ready`). `RevokeCertificate` sets `revoked_at` + audits; verify reads it
   live, so revocation is instant (no re-render).
4. **HTML render mirroring receipts + inline QR SVG.** `HtmlCertificateRenderer` builds the
   absolute verify URL `config('app.frontend_url').'/verify/'.$code`, renders the QR with
   endroid/qr-code's pure-PHP `SvgWriter` (no GD/Imagick), inlines it into a branded Blade
   (`certificates/default.blade.php`, which reads `$tenant->branding` colors/fonts — unlike
   the receipt view that hardcodes them), and stores `certificates/{tenant}/{code}.html` on
   s3. `RenderCertificate` mirrors `RenderReceipt` (queued, idempotent). Added
   `config('app.frontend_url')` (mapping the existing `FRONTEND_URL` env) so all deep links
   are absolute.
5. **Host-independent public verify.** `GET v1/verify/{code}` lives **outside** the
   `tenant.domain` group (like the webhook routes), takes no auth, is `throttle:30,1`, and
   looks the cert up by global `code` via `withoutGlobalScopes()`. Returns
   `{valid, revoked, student_name, course, issued_on, tenant, number}` or `{valid:false}`
   for an unknown code (no existence oracle). The QR points here, not at the file.
6. **Private file, signed student download.** The rendered HTML is never public;
   `GET me/certificates` and the admin resource return a 1h signed s3 URL only for rendered
   certs. Admin issue/revoke gated by `can:manage-rosters` (certs are roster/records
   actions; trainer + admin hold it).

## Consequences

- Verified on a seeded scratch DB + 12 Pest cases: issuance is idempotent (auto + manual
  converge) with a unique code/number, audits, and notifies; completing the last topic of
  a course auto-issues (incomplete or zero-topic → none); the render stores HTML containing
  the QR `<svg` + the absolute verify URL; the public endpoint verifies genuine certs with
  **no auth and no tenant host**, reports revoked as invalid, and doesn't leak unknown
  codes; the student sees only their own certs (cross-tenant + guest denied); admin
  issue/revoke is gated + audited.
- Test-infra note: because course completion now transitively renders to s3, the Feature
  suite fakes the `s3` disk globally (`tests/Pest.php`) so no test touches real object
  storage; the demo `CertificateSeeder` tolerates a storage-less env (cert stays pending,
  renders where MinIO/s3 exists).
- New: `endroid/qr-code` dep; `certificates` table + `Certificate` model/factory;
  `Events/CourseCompleted` + `MarkTopicCompleted` trigger + `IssueCertificateOnCompletion`
  listener; `IssueCertificate`/`RevokeCertificate` actions; `CertificateRenderer` +
  `HtmlCertificateRenderer` + `RenderCertificate` job + Blade; public
  `CertificateVerifyController`, student `MyCertificateController`, admin
  `CertificateController` (+ Request/Resource); `config('app.frontend_url')`;
  `certificate_ready` template; frontend public `/verify/[code]`, portal `/certificates`,
  admin certificates page.
- **P3.4 (a→c) is complete.** **Deferred (owner-visible, not gaps):** pass-gated
  certificates (completion-only this pass); PDF output (HTML now, same renderer seam later);
  a tenant logo on the cert (no branding logo key yet); LinkedIn "add to profile"; bulk
  issue from the roster; per-course templates. Next: **P3.5 — Content AI + reports**.
