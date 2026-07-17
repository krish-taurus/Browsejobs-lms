# Session Prompts — paste-ready, one per Claude Code session

Run each prompt in a fresh Claude Code session **rooted in this repo**
(`cd C:\Users\DESKTOP\browsejobs-lms` → `claude`). Use **plan mode** (Shift+Tab)
for anything non-trivial. After each session: skim the diff, confirm the green
checks, commit, `/clear`.

---

## Status (updated 2026-07-17)

**Phases 1–2 — COMPLETE.** P1.1–P1.8 (scaffold, tenancy, auth, curriculum/
batches, Zoom, reminders, public site, portals). P2.1 CRM · P2.2 payments ·
P2.3 fee ladder · P2.4 messaging · P2.5 conversion · P2.6 review/voucher ·
P2.7 support desk · P2.8 entitlement engine.

**Phase 3 — in progress.** P3.1 AI gateway/telemetry/labs · P3.2 coach panel ·
P3.3 AI tutor (RAG) · P3.4 MCQ automation · P3.4b assignment grading · P3.4c
certificates · P3.5a reports/digests · P3.5b content AI · P3.5c syllabus
generator · P3.5d-a support corpus + deflection · P3.5d-b triage/reply
drafts/themes · P3.6 leaderboards + points — all merged to `main`.
**Next: P3.7 — motivation engine + Market Pulse + Content Hub (last of Phase 3).**

**Environment facts for a fresh session:**
- API tests: SQLite in-memory (`php artisan test` in `apps/api`) — 528 passing
  as of P3.6.
  Pint: `./vendor/bin/pint`. Web: `npm run typecheck && npm run lint && npm run
  build` in `apps/web`. E2E: `npx playwright test` (chromium installed; needs
  both dev servers; visit the app on **localhost**, not 127.0.0.1 — cookie
  same-site). **Only one Next process may run at a time** — two sharing `.next`
  corrupt each other's artifacts and every admin spec fails on a dead sign-in
  button. Check nothing else holds :3000 before starting `npm run dev`.
- Docker works: `docker compose -f docker/docker-compose.yml up -d`
  (MySQL/Redis/Mailpit/MinIO). Local `.env` currently uses **SQLite**
  (`DB_CONNECTION=sqlite`), not MySQL — `migrate:fresh --seed` rebuilds it.
- Composer works from **PowerShell** (`composer …`), not Git Bash.
- Local admin: `test@example.com` / `password` (staff, 2FA off). Dev servers:
  `php artisan serve --port=8000` + `npm run dev` (:3000).
- Read `CLAUDE.md` + `docs/browsejobs-lms-requirements.md` (incl. §14 addendum)
  before building. ADRs 0001–0027 in `docs/adr/`.

---

## NEXT → P3.7 — Motivation engine + Market Pulse + Content Hub

Read `CLAUDE.md` and `docs/browsejobs-lms-requirements.md` fully (PRD §6.18 +
§6.19). This is **P3.7** from `docs/BUILD-PLAYBOOK.md` — the LAST Phase 3
milestone. Draft the detailed prompt from the playbook bullet before starting.

Headline requirements: consented offer-celebration broadcasts (named or
anonymous mode) with the personalized "Your Path to the Same" AI guidance card
built from each recipient's gap report (3 concrete actions + one-tap mock
booking — mocks are P4, so stub the booking CTA); celebration wall. Market
Pulse: curated-feed ingestion → daily AI digest with sources +
course-relevance tie-ins, dashboard card; WhatsApp/email weekly **opt-in
only**. Content Hub: YouTube RSS/API + Instagram Graph + manual podcast
entries → dashboard feed, watch tracking into engagement score.
Marketing-category WhatsApp requires explicit opt-in (P2.4 hub enforces
category rules — reuse it).

P3.6 notes for reuse: celebrations already have a lightweight pattern
(in_app_notifications fan-out in PointsService::grantBadge under
app/Support/Points, opt-out respected) — §6.18's consented offer celebrations
are the richer, consent-gated evolution of it. Content Hub watch events should
flow through RecordActivity so they feed the engagement score.


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
6. **Support policy answers (the last thing gating deflection).** The screen is
   built — `/admin/support/settings` → "Policy answers" — and 8 starter documents
   are seeded, every fact transcribed from the PRD. What is missing is the
   operational truth that drives most real ticket volume: batch-transfer rules,
   reschedule rules, recording-access windows, refund mechanics past the 30-day
   window, what actually happens on a missed EMI, hardware requirements. Nobody
   can write these but you — inventing them in code would put a promise in a
   student's hands that the company never made. **The deflection rate is a
   function of this list, not of the model.** Type them into the screen (no
   deploy needed) or send them over. Overlaps with input 4 (retention/refund
   windows in /privacy-policy, /terms, /refund-policy).
