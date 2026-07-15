# ADR 0001 — Laravel 11 despite composer security-advisory blocking

- **Status:** Accepted (revisit at launch per PRD §10)
- **Date:** 2026-07-15
- **Context:** P1.1 Scaffold

## Context

The PRD (§2) and CLAUDE.md mandate **Laravel 11 (PHP 8.3)** to match the existing
BrowseJobs CRM stack. During scaffolding, Composer 2.10's default
`policy.advisories.block` refused to install **any** `laravel/framework` 11.x
release — every version in the `^11.0` range (through the latest, v11.55.0) is
flagged by published security advisories that are only resolved in Laravel 12.

`composer create-project laravel/laravel:^11.0` therefore failed to resolve.

## Decision

- Honor the PRD: pin **Laravel 11** (installed `laravel/framework` **v11.55.0**,
  the newest 11.x — fewest open issues within the mandated major).
- Set `config.policy.advisories.block: false` in `apps/api/composer.json` so
  local dev and CI installs succeed. This is scoped to this project only; the
  global Composer config was left untouched.
- `composer audit` still reports the advisories (not silenced) — it just no
  longer hard-blocks installation.

## Consequences

- The scaffold builds and tests run today on the mandated stack.
- **This is a temporary posture.** PRD §10 (pre-launch security checklist)
  already requires a dependency/security pass before go-live. At that point:
  either (a) upgrade to a patched framework line, or (b) formally accept each
  advisory with justification and re-enable blocking for everything else.
- Anyone adding dependencies should run `composer audit` and not treat the
  disabled block as license to ship known-vulnerable packages.

## Alternatives considered

- **Ignore specific advisory IDs** (`policy.advisories.ignore`): the advisories
  span the entire 11.x line, so this degenerates to the same outcome with more
  churn and staler config.
- **Jump to Laravel 12:** conflicts with the PRD's explicit "matches existing
  CRM stack" rationale. Out of scope for a scaffold task; would need product
  sign-off.
