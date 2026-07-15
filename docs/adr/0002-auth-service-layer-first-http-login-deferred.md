# ADR 0002 — Auth service layer first; HTTP login + token strategy deferred to P1.8

- **Status:** Accepted — deferred items DELIVERED in the P1.8 second pass (2026-07-16)
- **Date:** 2026-07-15
- **Context:** P1.3 Auth, roles, magic links

## Context

P1.3 (PRD §4) calls for the eight roles, staff profiles + support-team
memberships, student OTP login, staff email+password+2FA, branded logins
(`/admin`, `/trainer`, `/student`), and the magic-link framework.

Two constraints shaped how far P1.3 goes:

1. **No API-token package is installed.** `laravel/sanctum` appears in
   `composer.lock` but is **not** vendored (no `HasApiTokens`, no config, no
   `personal_access_tokens` migration), and Composer is not available on the
   build machine's PATH, so it cannot be added in this session.
2. **The API-auth mechanism is a frontend-coupled decision.** Choosing Sanctum
   SPA cookie auth vs. bearer tokens, and building the actual `/admin`,
   `/trainer`, `/student` login *pages*, belongs with the Next.js portal shell
   (P1.8) — building session/CSRF plumbing now would be thrown away then.

## Decision

Ship P1.3 as the **complete, fully-tested auth service/domain layer**, plus the
one HTTP surface that is stable regardless of the token decision (magic-link
consume). Defer the login HTTP controllers, token/session issuance, and the
branded portal login pages to **P1.8**.

**Delivered now (tested, on SQLite):**

- Roles & permissions: 8 roles seeded, permission map, `HasRoles` trait, and a
  `Gate::before` hook (`$user->can('permission-slug')`; super-admin bypass).
- Audit trail: `audit_logs` + `AuditLogger`; `AssignRoleToUser` writes an entry
  (role changes are a mandatory audit event).
- OTP: `RequestOtp` / `VerifyOtp` actions — hashed codes, ≤10-min TTL,
  per-identifier rate limiting, single-use atomic consume, channel abstraction
  (`OtpNotifier` → `LogOtpNotifier`).
- Staff auth: `StaffLogin` (email+password) + `CompleteStaffTwoFactor`; 2FA is
  an emailed OTP second factor (reuses the OTP system; no TOTP package needed).
- Staff profiles + support-team memberships (models + relationships).
- Magic-link framework: `MagicLinkService` (signed, action-scoped, single-use,
  TTL — ≤15 min for `pay.*`/`auth.*`, 24 h otherwise) + `ConsumeMagicLink` +
  **working `GET /magic/{token}` consume-and-redirect endpoint** (`signed`
  middleware, atomic consume, auto-login).

**Deferred to P1.8 (with the portal shell):**

- Login HTTP controllers + Form Requests for OTP/staff endpoints.
- API-auth mechanism (install Sanctum; SPA cookie vs token) + CSRF hardening.
- The branded `/admin`, `/trainer`, `/student` login pages (tenant-themed from
  the P1.2 branding JSON).

## Consequences

- The hard, reusable core (the parts every later feature calls) is done and
  covered by 24 auth tests; wiring HTTP endpoints later is thin and mechanical.
- **P1.3 is not 100% of its playbook bullet.** The deferred items above must be
  completed in P1.8; this ADR is the tracking record so they are not lost.
- Staff 2FA is OTP-over-email today. If app-based TOTP is later required, add a
  package and a `two_factor_secret` column — the `two_factor_enabled` flag and
  flow already exist.

## Alternatives considered

- **Build session/CSRF login endpoints now:** rejected — the SPA-auth strategy
  is undecided without the frontend, so this is likely-throwaway plumbing.
- **Hand-roll a token table (reinvent Sanctum):** rejected — more surface area
  and security risk than simply adding Sanctum in P1.8.
