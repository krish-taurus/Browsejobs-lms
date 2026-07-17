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

**Phase 3 — COMPLETE.** P3.1 AI gateway/telemetry/labs · P3.2 coach panel ·
P3.3 AI tutor (RAG) · P3.4 MCQ automation · P3.4b assignment grading · P3.4c
certificates · P3.5a reports/digests · P3.5b content AI · P3.5c syllabus
generator · P3.5d-a support corpus + deflection · P3.5d-b triage/reply
drafts/themes · P3.6 leaderboards · P3.7 motivation/pulse/content — all merged.

**Phase 4 — in progress.** P4.1 AI mock interviewer core (ADR 0029) · P4.2
Real Interview Intelligence + transcript ingestion (ADR 0030) — merged.
**Next: P4.3 — voice mocks + quota enforcement.**

**Environment facts for a fresh session:**
- API tests: SQLite in-memory (`php artisan test` in `apps/api`) — 568 passing
  as of P4.2.
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
  before building. ADRs 0001–0030 in `docs/adr/`.

---

## NEXT → P4.3 — Voice mocks + quota enforcement

Read `CLAUDE.md`, `docs/browsejobs-lms-requirements.md` (PRD §6.6/§6.17) and
**ADRs 0029–0030** before building. This is **P4.3** from
`docs/BUILD-PLAYBOOK.md`. Draft the detailed prompt from the playbook bullet
before starting.

Headline requirements: voice mode via Vapi\Retell (webhook session
lifecycle, transcript → the P4.1 scorecard pipeline; mocked in tests);
quotas via the Entitlement Service — included per enrolment type (5/course
live, 2 self-paced; 1 unlocks per module completed), 10-min session cap with
graceful wrap-up, out-of-credits → one-tap top-up (₹249/1, ₹599/3 from
monetization settings); per-session cost logging into ai_events.

Reuse notes from P4.1/P4.2 (all merged): `mock_interviews` already has
`mode` ('text'|'voice') — voice reuses the same record, turns, scorecard
pipeline (`FinishMockInterview`) and PRI blend; quota fields
`voice_included_live`/`voice_included_self_paced` already exist on
monetization settings, and `EntitlementService` has credit wallets +
consume/grant plumbing (see the CV-credit flows for the pattern);
webhook-signature + fake-client patterns to copy: Zoom/Razorpay webhooks and
`FakeTranscriptionClient`/`FakeAiClient`. Bank-fed questioning arrives free —
voice uses the same `mock_interview.v2` prompt.


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
