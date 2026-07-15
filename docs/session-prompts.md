# Session Prompts — paste-ready, one per Claude Code session

Drafted from `docs/BUILD-PLAYBOOK.md`. Each prompt = one session = one commit.
Run each in a Claude Code session **rooted in this repo** (`~/browsejobs-lms`), use
**plan mode** (Shift+Tab) for anything foundational, review the plan, then build.
After each: skim the diff, confirm the green checks, commit, then `/clear` before the next.

**Every prompt carries the same three add-ons** the playbook bullets don't spell out:
1. **SQLite/no-Docker note** — Docker can't run on this machine yet (BIOS virtualization
   is off; needs VT-x enabled + WSL2 + reboot). Backend tests run against SQLite in-memory;
   queues use `sync`/fake. Docker becomes mandatory at **P1.5** (Redis/Horizon + MinIO).
2. **Explicit Definition of Done** including the cross-tenant denial test.
3. **Commit tag** `feat(Px.y): ...`.

Status: P1.1 Scaffold ✅ committed (`145946f`). Next up → P1.2.

---

## P1.2 — Tenancy

Read `CLAUDE.md` and `docs/browsejobs-lms-requirements.md` (PRD §3) fully before writing anything. This is **P1.2 — Tenancy** from `docs/BUILD-PLAYBOOK.md`.

**Environment note:** Docker is unavailable on this machine (BIOS virtualization is off). Do **not** try `docker compose up` or expect MySQL/Redis. Configure the Pest/PHPUnit suite to run against **SQLite in-memory** (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml`) so migrations and tests run with zero external services. Keep MySQL 8 as the documented dev/prod driver in `.env.example` — only the test connection changes.

**Build multi-tenancy per PRD §3:**
1. `tenants` migration + model: branding theme (JSON), feature flags (JSON), domains, batch numbering pattern, status. Indexes on lookup columns.
2. `BelongsToTenant` trait applying a global scope on `tenant_id`, plus auto-setting `tenant_id` on create from the resolved tenant context. Foreign key + index on `tenant_id` per CLAUDE.md.
3. Tenant resolution middleware: **by domain** for public routes, **by authenticated user** for portal routes. Bind the resolved tenant into a request-scoped context the global scope reads from.
4. Seeder: tenant 1 = **BrowseJobs**, seeded with the full design-system theme from CLAUDE.md (all color variables, fonts) as its branding JSON, so it's the default tenant theme.
5. The **reusable cross-tenant denial test pattern**: a helper/base that asserts tenant A cannot read or write tenant B's rows. This is the pattern every future feature's test suite will reuse — make it clean and documented.

**Follow CLAUDE.md conventions:** PHP 8.3 `declare(strict_types=1)`, PSR-12/Pint, thin controllers → Form Requests → Action classes, no logic in models.

**Definition of Done:** migrations + model + trait + middleware + seeder; Pest tests covering (a) tenant scoping works, (b) cross-tenant read denial, (c) cross-tenant write denial, (d) domain-based vs user-based resolution; `composer test` green on SQLite; `composer lint` clean. If anything in PRD §3 is ambiguous, decide, implement, and record an ADR in `docs/adr/` — don't guess silently.

Commit as `feat(P1.2): multi-tenancy foundation` when green.

---

## P1.3 — Auth, roles, magic links

Read `CLAUDE.md` and `docs/browsejobs-lms-requirements.md` (PRD §4) fully first. This is **P1.3 — Auth, roles, magic links** from `docs/BUILD-PLAYBOOK.md`. It builds on the P1.2 tenancy foundation — every auth model is tenant-scoped via `BelongsToTenant`, and every test reuses the P1.2 cross-tenant denial pattern.

**Environment note:** Docker is unavailable (BIOS virtualization off). Tests run against **SQLite in-memory** — no MySQL/Redis. For anything needing a queue in tests, use Laravel's `sync`/fake drivers. Never hit real external services.

**Build auth per PRD §4:**
1. **The 8 roles + permissions** — model them exactly as the PRD defines (roles + granular permissions, gates/policies). Role changes must write an **audit log entry** (per CLAUDE.md).
2. **Staff profiles** — description field + support-team memberships (a staff member can belong to multiple support teams; this feeds ticket routing later in P2.7).
3. **Student login** — phone **and** email **OTP** flow: request OTP → verify → session. Rate-limit OTP request + verify endpoints (per CLAUDE.md). OTP delivery behind a channel abstraction (log-driver locally; real adapters come later) — do not call a real SMS/email provider.
4. **Staff login** — email + password + **2FA**. Rate-limit auth endpoints.
5. **Branded login routes** — `/admin`, `/trainer`, `/student`, each tenant-themed via the P1.2 branding JSON.
6. **Magic-link framework** — signed, **single-use**, **action-scoped** (one link → one action), expiry per CLAUDE.md (**≤24h general, ≤15min for payment/auth actions**), **consumed atomically** (no double-spend under concurrency). Provide a generic **consume-and-redirect endpoint** that validates signature + expiry + unused state, marks it consumed in one atomic step, then redirects to the action target. This framework is reused everywhere (reminders, payments, reviews, mocks) — make it clean and generic.

**Follow CLAUDE.md conventions:** PHP 8.3 `declare(strict_types=1)`, PSR-12/Pint, thin controllers → Form Requests → Action classes. All auth/OTP/magic-link models tenant-scoped.

**Definition of Done:** migrations + models + Actions + Form Requests + endpoints; Pest tests covering — OTP request/verify happy path + wrong/expired OTP, magic link **single-use** (second consume rejected), magic link **expired** rejected, **action-scope mismatch** rejected, atomic consume under concurrent hits, role/permission **gates** (each role can/can't do representative actions), staff 2FA flow, and the **cross-tenant denial** test on auth models. Rate-limit tests where practical. `composer test` green on SQLite; `composer lint` clean. Ambiguity in §4 → decide, implement, record an ADR in `docs/adr/`.

Commit as `feat(P1.3): auth, roles, magic-link framework` when green.

---

## P1.4 — Curriculum + batches

Read `CLAUDE.md` and `docs/browsejobs-lms-requirements.md` (PRD **§6.1 and §6.2**) fully first. This is **P1.4 — Curriculum + batches** from `docs/BUILD-PLAYBOOK.md`. **Scope excludes the AI syllabus generator** (that's P3.5). Builds on P1.2 tenancy + P1.3 auth/roles. This is the **first task with real admin UI**, so Next.js conventions in CLAUDE.md now apply.

**Environment note:** Docker unavailable (BIOS virtualization off). Backend tests run against **SQLite in-memory**; queues use `sync`/fake. Playwright runs against the local `apps/web` dev server + `apps/api` — no Docker needed for that.

**Build per PRD §6.1 (curriculum) and §6.2 (batches, minus AI syllabus gen):**
1. **Curriculum hierarchy** — `programs → courses → modules → topics → lessons`, full CRUD in the **admin portal**. Tenant-scoped, role-gated (per P1.3 permissions). FK + indexes on `tenant_id` and parent IDs.
2. **Batch management** — auto numbering pattern `{COURSE}-{YYYYMM}-{seq}` with **manual override**; batch types **masterclass / bootcamp / paid**; capacity guards. Pull the numbering pattern from the tenant config (P1.2) where applicable.
3. **Roster** — silo add (single), **bulk multi-select** add, **CSV import** (validated, with error reporting), **transfer** between batches, **remove** — transfer/remove/roster changes all write **audit log entries** (per CLAUDE.md). **Per-member statuses**. FK + indexes on `student_id`, `batch_id`.
4. **Domain events** — fire `TopicCompleted` and `ModuleCompleted` as first-class events at the right points. **Listeners come later** — just emit them cleanly now (no inline side effects).
5. **Seed** — the **7 BrowseJobs courses** with sample curriculum (modules/topics/lessons) so the feature is demo-able immediately.

**Conventions:** Backend — PHP 8.3 `declare(strict_types=1)`, PSR-12/Pint, thin controllers → Form Requests → Action classes (`app/Actions/{Domain}/`), events first-class. Frontend — App Router, Server Components by default, TypeScript strict (no `any`), Zod at API boundaries, Tailwind design tokens only (no hardcoded hex), React Query for server state. Admin CRUD needs loading/empty/error states per the DoD.

**Definition of Done:** migrations + models + Actions + Form Requests + API Resources; admin UI wired with loading/empty/error states; events emitted; Pest tests (curriculum CRUD happy path, capacity guard, CSV import valid + invalid rows, transfer/remove **audit** assertions, event emission, batch auto-numbering + manual override, **cross-tenant denial** on curriculum + batch + roster models) and a **Playwright** test for the admin create-batch-and-roster flow; seed data present; `composer test`, `composer lint`, and `npm run typecheck` all green. Ambiguity in §6.1/§6.2 → decide, implement, ADR in `docs/adr/`.

Commit as `feat(P1.4): curriculum + batch management` when green.

---

## P1.5 onward

Not pre-drafted — write each from its `docs/BUILD-PLAYBOOK.md` bullet once P1.4 lands and the
codebase shape is known. Same recipe: playbook bullet + SQLite/Docker note + explicit DoD with
cross-tenant denial + commit tag. **P1.5 (Zoom) is the first task that requires Docker** (Redis/Horizon,
MinIO) — enable BIOS VT-x + install WSL2 + reboot before starting it.
