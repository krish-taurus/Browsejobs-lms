# ADR 0039 — Platform integration settings (admin-managed keys)

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.14 (admin, settings & compliance)
- **Relates to:** ADR 0016 (multi-LLM provider config), P2.4 (WhatsApp), P4.3 (Vapi voice), P1.5 (Zoom)

## Context

Every third-party key — LLM providers, WhatsApp, Vapi, Zoom — lived only in `.env`, so
changing a key or switching LLM provider meant a redeploy, and nothing was manageable from
the panel. The founder asked to enter all of these in the admin, add multiple LLM keys, and
surface Vapi/Retell and WhatsApp (which had no UI at all). The agreed scope: **platform-global**
(one set of keys for the deployment, not per-tenant), editable in an admin Settings area.

This is a security-sensitive feature — it stores live credentials — so the design is built
around never exposing or leaking a secret.

## Decision

1. **A whitelist config drives everything.** `config/platform_settings.php` declares each
   manageable field once: its `group`, `key`, `type` (`text`/`secret`/`select`), and the
   `config` dot-path it overrides. The service, the API schema, the validation, the masking,
   and the runtime override all read this one list. A `group.key` that isn't listed can never
   be stored or applied — the UI cannot inject an arbitrary config path.

2. **Stored in `platform_settings`, encrypted at rest.** A key-value table (no `tenant_id` —
   platform-global) with the value under Laravel's `encrypted` cast, so the column is
   ciphertext. A test asserts the plaintext key never appears in the raw column.

3. **DB overrides `config()` at boot; `.env` is the fallback.** `PlatformSettings::apply()`
   runs at the top of `AppServiceProvider::boot`, before any client binding resolves, and
   sets `config([...])` from stored values. Every client (AI, WhatsApp, Vapi, Zoom) already
   reads `config()` lazily in its binding, so none of them changed. A blank/missing setting is
   skipped, leaving the env default in place. Fail-safe: if the table is absent (fresh install,
   mid-migration) or the DB is unreachable, `apply()` returns quietly and the app runs on env.

4. **Secrets never leave the server.** The API schema returns `set: true/false` and a masked
   `••••<last4>` hint for secret fields — never the value. Non-secret fields (provider choice,
   base URLs, model names) round-trip normally. A blank secret field on save means "keep the
   current value", so an empty box can't wipe a stored key; a blank *non-secret* clears.

5. **Super-admin only, and audited without values.** The routes are gated `can:manage-settings`,
   a permission no role is granted — only the `Gate::before` super-admin bypass satisfies it, so
   these platform-wide keys are out of a plain institute admin's reach. Every save writes an
   audit entry listing *which* keys changed, never their values. The nav hides the Settings link
   from non-super-admins (the API enforces it regardless).

## Consequences

- **Positive:** Keys are managed from the panel; LLM provider switches instantly (all provider
  keys can be stored, one is active); WhatsApp and Vapi finally have a config surface. Adding a
  new manageable setting is one entry in the whitelist — no service, controller, or UI change.
- **Trade-offs:** Platform-global, not per-tenant — deliberate per the agreed scope; true
  whitelabel per-tenant credentials would be a larger follow-up keyed by tenant. `apply()` reads
  the settings table once per request (singleton-cached); negligible, and only when the table
  exists.
- **Retell** is named in the Vapi group's help text but has no client yet — it slots behind the
  same `VoiceMockClient` interface when built; no config field is exposed for it until then.
- **Deferred:** per-tenant credentials; a "test connection" button per integration; rotating a
  key on a schedule; surfacing which features are currently live vs. keyless.

## Verification

`php artisan test` — full suite **670 passing** (661 + 9), incl.
`Feature/Admin/PlatformSettingsTest`: schema returns **no secret values** (masked hint only);
a submitted secret is **encrypted at rest** (plaintext absent from the raw column); a blank
secret **leaves the existing value untouched**; stored keys **override config so the clients use
them**; a blank setting does **not** override env; **non-whitelisted keys are ignored**; the audit
**records keys but not values**; and **super-admin-only** (a plain admin and a student are both
denied). Pint clean. `npm run typecheck`, `lint`, `build` pass. Fresh `migrate --seed` applies the
new table with the boot-time override active. The admin Settings page (LLM / WhatsApp / Voice AI /
Zoom sections, password-masked secrets, blank-keeps-current) is reachable only by a super-admin.
