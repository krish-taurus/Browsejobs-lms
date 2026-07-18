# Launch-Readiness Audit — PRD §10 Security & Ops Checklist (P4.9c)

**Date:** 2026-07-18 · **Scope:** every item in PRD §10, audited against the codebase at `main`.
**Legend:** ✅ Done in code · ⚙️ Ops/config task (code-ready, action needed at deploy) · ⚠️ Gap (fix below).

| # | §10 item | Status | Evidence / action |
|---|----------|--------|-------------------|
| 1 | Rotate ALL API keys (Razorpay, Zoom, Anthropic, WhatsApp) | ⚙️ Ops | No secret literals in code — verified `grep` finds none in `app/`, `config/`; every key reads from `config()`/env, and admin-entered keys are encrypted (`PlatformSetting`). **Action:** rotate & set real keys in the production secret store before go-live; never reuse anything pasted in chats. |
| 2 | Webhook signature verification (Razorpay, Zoom, WhatsApp) | ✅ Done | `Webhooks/{Razorpay,Zoom,WhatsApp,MetaLead}Controller` verify HMAC signatures and reject unsigned payloads before processing. |
| 3 | Magic links: single-use, short expiry, action-scoped | ✅ Done | `MagicLinkService::create` (signed route, ≤24h / ≤15m for pay/auth, action-scoped, hashed token); `ConsumeMagicLink` consumes atomically (`UPDATE … WHERE consumed_at IS NULL`). |
| 4 | Rate limiting on auth + AI; per-student daily AI token budget | ✅ Done | `throttle:6,1` on OTP/login/register, `throttle:ai` (20/min) on AI endpoints (`RateLimiter::for('ai'|'api')`); `AiGateway` enforces `ai.daily_token_budget` per student and throws `AiBudgetExceeded`. |
| 5 | Cross-tenant access tests must fail | ✅ Done | `BelongsToTenant` global scope on every domain model; **38 feature test files** carry cross-tenant / "does not leak" assertions. Full suite green (783 tests). |
| 6 | PII encryption at rest; TLS everywhere | ✅ / ⚙️ | At rest: interview transcript originals (`Crypt`), platform-setting secrets encrypted. **Action:** TLS is infra — terminate at the LB, HSTS on, DB/Redis connections encrypted in the VPS setup. |
| 7 | Telemetry consent at signup (DPDP) | ✅ Done | **Closed.** Registration now requires explicit consent (incl. the activity-monitoring telemetry disclosure) and stamps `users.telemetry_consent_at` + `consent_version` (`config('dpdp.consent_version')`) when the account is created; the consent checkbox on the register form links privacy/terms, and the record is included in the DPDP export. |
| 8 | Voucher tied to platform testimonials, not Google reviews | ✅ Done | Vouchers issue only from verified platform `Testimonial`s (`IssueVoucher`, review-for-voucher engine); Google-review policy guard in the UI copy. |
| 9 | Queue workers supervised + failure alerting | ⚙️ Ops | Horizon-ready; every side effect is an idempotent queued job. **Action:** run Horizon under supervisor/systemd, wire failed-job + queue-depth alerting. |
| 10 | Staging with Razorpay test mode + Zoom sandbox | ⚙️ Ops | All external clients are transport-swappable (Fake in tests, Null when unkeyed). **Action:** stand up staging with test-mode keys + Zoom sandbox before the witnessed demo. |
| 11 | Load target: 500 concurrent students · 20 live classes · 1,000 masterclass registrants | ⚙️ Ops | See **Load-test plan** below. Code is stateless-request + queue-backed; targets are validated by a staging load run, not unit tests. |

## Gap #7 — CLOSED

Registration now captures a per-account DPDP consent:

- `users.telemetry_consent_at` + `consent_version` are stamped by `RegisterController::verify` from
  `config('dpdp.consent_version')` when the account is created.
- `RegisterRequest` requires `consent` (`accepted`), so the OTP is never even sent — let alone an
  account created — without an explicit agreement; the register form carries the checkbox linking the
  privacy policy + terms and disclosing activity monitoring.
- The consent record is included in the DPDP access export (`DataExporter`).

Covered by `Feature/Auth/RegisterTest` (consent stamped on success; registration rejected + no OTP
without consent). **All 11 §10 items are now satisfied in code or code-ready pending a deploy-time ops
action.**

## Load-test plan (item 11)

Targets (PRD §10): **500 concurrent students**, **20 concurrent live classes**, **1,000 concurrent masterclass registrants**.

Run against staging (test-mode keys) with k6 or Artillery — never production, never a live payment/Zoom account:

1. **Masterclass registration spike (1,000 concurrent):** POST the public registration + lead endpoints (already `throttle:10,1` per IP — load-test from distributed IPs or raise the limit for the staging run). Assert p95 < 800ms, 0 dropped registrations, reminder jobs enqueued.
2. **Student portal steady-state (500 concurrent):** authenticated mix of `GET me/coach`, `me/jobs`, `me/placement`, lesson/recording reads. Assert p95 < 500ms, DB connection pool stable, Redis hit-rate healthy.
3. **Live-class join burst (20 classes × ~50 students):** hit the gated Join endpoint + Zoom-webhook attendance ingestion (sandbox). Assert FeeGate + attendance writes keep up, queue depth drains within SLA.
4. **Queue soak:** confirm Horizon drains reminder/AI/notification jobs under sustained load without backlog growth; watch `ai_events` cost and the per-student budget guard.

Record throughput, p50/p95/p99, error rate, and queue depth; size the VPS (4GB+ RAM start, scale workers/DB per results) from the numbers.

## Keys to rotate before go-live (item 1)

Razorpay (key id + secret + webhook secret) · Zoom (S2S OAuth account/client/secret) · Anthropic / active `AI_PROVIDER` key · WhatsApp Cloud API (phone id + access token) · Judge0 · Vapi (if voice mocks enabled) · app `APP_KEY`. Set them in the production secret store; `.env.example` keeps placeholder names only.

## Verdict

**All 11 items are satisfied in code or code-ready pending a deploy-time ops action** (gap #7 closed post-audit). Complete the ⚙️ ops items on staging (key rotation, TLS, Horizon supervision + alerting, staging with test-mode keys, the load run), then run the witnessed ad-link-to-placement demo — the launch gate.
