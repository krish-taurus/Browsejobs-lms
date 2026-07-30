# ADR 0051 — Employer Workspaces: first company entity, membership-scoped roles

**Status:** Accepted · **Date:** 2026-07-30 · **Relates to:** PRD-E v1.0 (`docs/employer-module-requirements.md`), ADR 0034 (placement pipeline), ADR 0037 (candidates directory)

## Context

The employer module (PRD-E) introduces demand-side accounts. Until now, every "company" in the schema is a plain string (`job_postings.company`, `job_feed_items.company`, `interview_transcripts.company`), hiring partners are explicitly *not* platform users (`hiring_partner_feedback` uses unguessable public tokens), and all authenticated users are staff or students.

Three decisions needed making:

1. **Entity model** — is an employer a new entity or an extension of `job_postings`?
2. **Authorization** — platform `Role`/`Permission` rows (the `Gate::before` slug system) or something workspace-scoped?
3. **User identity** — new user table or the existing `users` table?

## Decision

1. **New entities, existing modules untouched.** `employer_workspaces`, `employer_members`, `employer_invites` are new tables. The existing staff-curated `job_postings` board is left as-is; employer-owned JDs (F2) will live in a separate `employer_jobs` table rather than adding employer ownership to `job_postings`. If the two boards later merge, that is a deliberate future migration — not an implicit coupling now. The `hiring_partner_feedback` token flow continues unchanged for non-registered companies; registered employer workspaces are the upgrade path.

2. **Workspace roles are membership attributes, not platform roles.** `employer_members.role` holds an `EmployerRole` enum (`owner | recruiter | hiring_manager`). Platform `Role` rows are global staff concepts and the `can:` route gates resolve global permission slugs — wrong shape for "owner of workspace A, hiring manager in workspace B". Employer routes are gated by `auth:sanctum` + `tenant.user`; controllers resolve the caller's membership via `App\Support\Employers\ResolvesMembership` and enforce role capabilities from enum helpers (`managesWorkspace()`, `managesPipeline()`). PRD-E §2's "policy-based" wording is satisfied by this centralized trait; we deliberately do **not** introduce Laravel Policy classes, which the codebase has zero of — consistency wins.

3. **Employer users are `users` rows** with `user_type = 'employer'` (plain string column, additive value). They authenticate through the existing Sanctum SPA flow and resolve tenant via `tenant.user`. A user's memberships define workspace access; a person can belong to multiple workspaces (agency case).

## Consequences

- Cross-tenant isolation is inherited from `BelongsToTenant`/`TenantScope`; every employer feature additionally needs the cross-*workspace* denial test (two workspaces, same tenant), which F1's suite establishes.
- Invites follow the magic-link rules: 64-char random token, single-use (atomic guarded UPDATE on `accepted_at`), expiring (`config('employers.invite_ttl_days')`, default 7), and the raw token is never serialized in API resources — it travels only in the invite email.
- Audit entries are written for workspace registration, invites, joins, role changes, and member removals (CLAUDE.md: role/roster changes must be audited).
- Credit caps (F8) and other server-owned limits live in `config/employers.php`.
- Integration edits to shared files are additive-only: an import block + one route group in `routes/api.php`, one seeder registration in `DatabaseSeeder`.
