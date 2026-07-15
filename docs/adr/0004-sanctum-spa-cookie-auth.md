# ADR 0004 — Sanctum SPA cookie auth for the Next.js frontend

- **Status:** Accepted (resolves the auth deferral in ADR 0002)
- **Date:** 2026-07-15
- **Context:** P1.8 — frontend + API auth

## Context

ADR 0002 deferred the API-auth mechanism to P1.8 because it is coupled to the
frontend. P1.8 needs the Next.js app (student/staff portals) to authenticate
against the Laravel API. Two options: Sanctum SPA **cookie** auth (stateful,
same-site session) vs. Sanctum **bearer tokens** stored in the SPA.

## Decision

Use **Laravel Sanctum SPA cookie authentication** (chosen by the product owner).

- Installed `laravel/sanctum ^4.3`; `User` uses `HasApiTokens`.
- `bootstrap/app.php` enables `$middleware->statefulApi()` so first-party
  requests authenticate via the session cookie; `/api/v1/me` and `/logout` are
  guarded by `auth:sanctum`.
- `config/cors.php` sets `supports_credentials: true` and allows the frontend
  origins (`FRONTEND_ORIGINS`).
- Stateful domains via `SANCTUM_STATEFUL_DOMAINS`; shared cookie via
  `SESSION_DOMAIN`.
- Login endpoints (`routes/api.php`, tenant resolved by host) wrap the P1.3
  actions and establish the session:
  - `POST /api/v1/auth/otp/request` + `otp/verify` — student phone/email OTP
  - `POST /api/v1/auth/staff/login` + `staff/2fa` — staff password + emailed 2FA
  - `POST /api/v1/logout`, `GET /api/v1/me`
- `config/tenancy.php` adds a `default_slug` fallback so single-tenant/local
  hosts (localhost) resolve without a domain mapping; unset in true multi-tenant
  environments so unknown hosts still 404.

The SPA flow: `GET /sanctum/csrf-cookie` → POST credentials (with `Origin`) →
session cookie is set → subsequent requests are authenticated and CSRF-checked.

## Consequences

- No tokens live in JS — lower XSS blast radius than bearer tokens; CSRF is
  enforced by Sanctum for stateful requests.
- Frontend and API should be same-site in production (e.g. `app.browsejobs.ai`
  + `api.browsejobs.ai` with `SESSION_DOMAIN=.browsejobs.ai`).
- The branded `/admin` `/trainer` `/student` **login pages** and remaining
  portal/admin UI now build against these endpoints (the rest of ADR 0002/0003).
- `personal_access_tokens` table exists (published by Sanctum) but is unused by
  the SPA flow; available if a mobile/token client is added later.

## Alternatives considered

- **Bearer tokens in the SPA:** simpler cross-origin story but tokens are
  exposed to XSS and need manual refresh/rotation. Rejected for a first-party
  same-site web app.
