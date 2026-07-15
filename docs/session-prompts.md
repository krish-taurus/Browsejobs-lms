# Session Prompts — paste-ready, one per Claude Code session

Run each prompt in a fresh Claude Code session **rooted in this repo**
(`cd C:\Users\DESKTOP\browsejobs-lms` → `claude`). Use **plan mode** (Shift+Tab)
for anything non-trivial. After each session: skim the diff, confirm the green
checks, commit, `/clear`.

---

## Status (updated 2026-07-16)

**Phase 1 — COMPLETE and pushed to main (commit `2a06b8d`).**
P1.1 scaffold · P1.2 tenancy · P1.3 auth layer · P1.4 curriculum/batches ·
P1.5 Zoom · P1.6 reminders/cancel-reschedule · P1.7 public site · P1.8 both
passes (Sanctum SPA auth, student portal, staff login + admin panel).
Also delivered beyond the playbook: Platform Spec v1.0 alignment (ADR 0005),
brochure-sourced course detail pages (DE excludes Kafka/streaming from core —
carried as optional self-study), /reviews wall + import pipeline, /register +
config-gated Google sign-in, lead capture with UTM + DPDP consent.

**Environment facts for a fresh session:**
- API tests: SQLite in-memory (`php artisan test` in `apps/api`) — 99 passing.
  Pint: `./vendor/bin/pint`. Web: `npm run typecheck && npm run lint && npm run
  build` in `apps/web`. E2E: `npx playwright test` (chromium installed; needs
  both dev servers; visit the app on **localhost**, not 127.0.0.1 — cookie
  same-site).
- Docker works: `docker compose -f docker/docker-compose.yml up -d`
  (MySQL/Redis/Mailpit/MinIO). Local `.env` uses MySQL.
- Composer works from **PowerShell** (`composer …`), not Git Bash.
- Local admin: `test@example.com` / `password` (staff, 2FA off). Dev servers:
  `php artisan serve --port=8000` + `npm run dev` (:3000).
- Read `CLAUDE.md` + `docs/browsejobs-lms-requirements.md` (incl. §14 addendum)
  before building. ADRs 0001–0005 in `docs/adr/`.

---

## NEXT → P2.1 — Built-in CRM

Read `CLAUDE.md` and `docs/browsejobs-lms-requirements.md` fully (PRD §6.12 and
the §14 addendum). This is **P2.1 — Built-in CRM** from `docs/BUILD-PLAYBOOK.md`.

**Environment:** backend tests on SQLite in-memory with `sync`/fake queues —
never call real external APIs in tests (fake the Meta webhook signature the
same way `tests/Feature/LiveClasses/ZoomWebhookTest.php` fakes Zoom's). The
frontend admin panel pattern to follow is `apps/web/src/app/admin/(panel)/*`
(React Query + AdminShell; staff-role guard).

**Already exists — build on it, don't recreate:** a `leads` table + `Lead`
model + public `POST /api/v1/leads` capture endpoint (UTM + logged DPDP
consent) fed by the site's lead modal, and an `AuditLogger`.

**Build PRD §6.12:**
1. **Lead stages + inbox** — `lead_stages` (per-tenant, seeded default
   pipeline) and a `stage`/`assigned_to`/`score` on leads; admin leads inbox
   (list, filters by type/stage/course/UTM, search) at `/admin/leads`.
2. **Capture sources wired** — existing site modal already lands leads; add
   CSV import + manual create in the admin UI, and the **Meta Lead Ads webhook
   endpoint** with X-Hub-Signature-256 verification (reject unsigned; mocked
   in tests) mapping to leads.
3. **Phone dedupe + merge** — detect duplicates per tenant by phone; merge
   tool that folds timelines/fields with an audit entry.
4. **Pipeline kanban** — lead stages as columns with drag-drop (or
   move-to-stage action buttons if drag-drop fights the timebox) + filters.
5. **Contact timeline** — `contact_timeline_events` aggregating lead events
   (created, stage change, assignment, notes, masterclass registration);
   render on a lead detail drawer/page.
6. **Counselor assignment** — round-robin and by-course rules
   (admin-configurable), auto-assign on capture, manual reassign with audit.
7. **Task queue** — `crm_tasks` with due dates, assignee, done state; "my
   tasks" view for counselors.
8. **Speed-to-lead SLA timers** — first-touch SLA per lead (configurable
   minutes), countdown surfaced in the inbox, breach flag + counselor task on
   breach (queued job; sync in tests).
9. **Lead scoring stub** — rule-based score field + service interface (real
   engagement inputs arrive in P3).

**Conventions:** PHP 8.3 strict, thin controllers → Form Requests → Actions,
events + queued listeners for side effects, `BelongsToTenant` on every new
model, FK+indexes on tenant_id/lead ids. Frontend: React Query, design tokens
only, loading/empty/error states, one blue primary CTA per view.

**Definition of Done:** migrations + models + Actions + endpoints + admin UI;
Pest tests — happy paths, Meta webhook signature accept/reject, dedupe/merge
(+ audit), assignment rules, SLA breach job, and **cross-tenant denial** on
every new model; audit entries for merge/reassign/stage overrides; seed a demo
pipeline so `/admin/leads` is demo-able; `php artisan test`, Pint, `npm run
typecheck`, `npm run build` all green. Ambiguity in §6.12 → decide + ADR.

Commit as `feat(P2.1): built-in CRM` when green.

---

## After P2.1

**P2.2 — Payments + EMI** (Razorpay orders/verify/webhook, ₹30,000 registration
+ 3×₹10,000 EMI per spec §3.6, server-owned amounts, GST receipt PDF, ledger) —
draft its prompt from the playbook bullet + spec §9 when P2.1 lands.

---

## Outstanding founder inputs (blocked on Krish — not on code)

1. **Reviews CSV** — real Google/WhatsApp review exports
   (`author_name,rating,body,source,reviewed_on,course_slug`), then:
   `php artisan reviews:import <file>`. The /reviews wall renders 1,000+
   smoothly; reviews are never fabricated.
2. **Google sign-in keys** — set `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`
   in `apps/api/.env` (redirect URI `{APP_URL}/auth/google/callback`); the
   button then appears on /student and /register automatically.
3. **Python Backend brochure** — the /courses/python-backend page defers to
   counselling until the syllabus arrives.
4. **Legal placeholders** — [CIN], [GST], Grievance Officer name +
   retention/refund windows in /privacy-policy, /terms, /refund-policy.
5. **Zoom + Razorpay + WhatsApp credentials** — needed before P2.2+ can be
   exercised against real sandboxes (tests stay mocked regardless).
