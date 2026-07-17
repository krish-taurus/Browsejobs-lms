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
drafts/themes — all merged to `main`. **P3.5 is complete.**
**Next: P3.6 — leaderboards + points.**

**Environment facts for a fresh session:**
- API tests: SQLite in-memory (`php artisan test` in `apps/api`) — 435 passing
  as of P3.5c.
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
  before building. ADRs 0001–0025 in `docs/adr/`.

---

## NEXT → P3.6 — Leaderboards + points

Read `CLAUDE.md` and `docs/browsejobs-lms-requirements.md` fully (PRD §6.16).
This is **P3.6 — Leaderboards + points** from `docs/BUILD-PLAYBOOK.md`. Draft the
detailed prompt from the playbook bullet + §6.16 before starting.

Headline requirements: `points_events` emitted from attendance, quizzes, labs,
mocks, streaks, and punctuality with **admin-configurable weights**; a batch
leaderboard on the student dashboard (weekly + all-time); **top-10 named with
opt-out, everyone else sees only their own rank + distance-to-next** —
motivating, never humiliating; badges with batch-feed celebrations; coach
integration ("one mock closes the gap to #3"). **Anti-gaming: points only from
verified events, daily caps.**

**P3.5 is complete** (ADRs 0024, 0025). Two things it left behind that P3.6 does
not need, but someone should pick up:

1. **Staff cannot author support policy documents.** The deflection corpus is
   seeder-only (`SupportCorpusSeeder`); an admin CRUD over `knowledge_documents`
   where `source_type=support` is the missing piece. Until it lands the corpus
   only changes by deploy — and deflection quality is gated on the corpus, not
   the model (see the founder input below).
2. **`e2e/admin-support-triage.spec.ts` has never run green.** Not a code defect:
   the local box had two Next processes sharing one `.next`, and the
   **pre-existing** `admin-support-desk.spec.ts` fails identically in that state.
   Run the e2e suite with a single Next dev server on **:3000** — Sanctum's
   stateful domains and CORS are pinned to that port, so another port fails login.

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
6. **Support corpus source-of-truth (blocks P3.5d-a quality).** Deflection can
   only answer from documents that exist; the KB today indexes course content
   only. The PRD fee model covers registration/EMI/placement-fee/money-back, but
   the real 30–50% of ticket volume is operational: reschedule rules, recording
   access windows, batch-transfer policy, refund mechanics, what happens on a
   missed EMI. Either confirm the PRD text is the whole truth, or supply the
   actual policy answers. **The deflection rate is a function of this list, not
   of the model** — shipping the code against a thin corpus produces a feature
   that looks built and deflects nothing. Overlaps with input 4 (retention/
   refund windows in /privacy-policy, /terms, /refund-policy).
