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
Real Interview Intelligence + transcript ingestion (ADR 0030) · P4.3 voice
mocks + quotas incl. AI_PROVIDER voice brain (ADR 0031) · P4.4 native mentor
scheduling, course-scoped multi-mentor calendar (ADR 0032) · P4.5a AI CV
generator + ATS suite (ADR 0033) · P4.5b placement pipeline: pool gating,
job board, application kanban, debrief→bank, consent-gated celebrations,
Proof Engine (ADR 0034) — merged.
**Next: P4.6 — review protection + job-probability boosters** (founder may
pull **P4.8 Live Job Feed + Apply Assist** forward instead — ask).

**Environment facts for a fresh session:**
- API tests: SQLite in-memory (`php artisan test` in `apps/api`) — 628 passing
  as of P4.5b. Local `.env` sets PLACEMENT_MIN_PRI=0 for demos (tests pin 70
  in phpunit.xml; production default is 70).
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
  before building. ADRs 0001–0034 in `docs/adr/`.

---

## NEXT → P4.6 — Review protection + job-probability boosters
*(founder may pull P4.8 forward instead — confirm before starting)*

Read `CLAUDE.md`, `docs/browsejobs-lms-requirements.md` (PRD §6.20) and
**ADRs 0029–0034** before building. This is **P4.6** from
`docs/BUILD-PLAYBOOK.md`. Large — consider splitting retention mechanics
(NPS/rage/pause/onboarding) and boosters (LinkedIn/GitHub/prep packs +
Career+) into two branches.

Headline requirements: NPS pulses at week-1/mid/pre-placement (9–10 →
unconditioned Google-review routing; ≤6 → instant counselor rescue task +
admin alert); rage-signal detection (failed payments, low CSATs,
engagement cliffs, angry sentiment) → priority interventions;
pause/defer enrolment workflow (admin rules; the refund-dispute killer);
week-1 white-glove onboarding checklist + call task; LinkedIn profile
optimizer; GitHub portfolio auto-builder from lab projects; application
tracker AI tailoring; interview-day prep packs from the real-interview
bank; Career+ subscription (₹499/mo product exists on the Entitlement
Service since P2.8).

Reuse notes: NPS → Messenger templates + magic links (P3.4 dispatch
pattern); rescue tasks → counselor flows (see CheckQuizCompletion's flag
phase + support ticket routing); rage signals read existing telemetry,
payments (P2.2 failures), CSAT (P3.5d tickets) and risk flags (P3.2);
prep packs pull approved `real_interview_questions` by role (ADR 0030);
LinkedIn/GitHub boosters follow the CV pattern — facts from
CvProfileData + profile, AI rephrases, never invents (ADR 0033); the
application tracker lives in `job_applications` (ADR 0034 — add the
AI-tailoring hook there); Career+ gates boosters via
`hasEntitlement('career_plus')`.

### Alternative if pulled forward: P4.8 — Live Job Feed + Apply Assist
PRD §6.22 + playbook P4.8: `JobFeedSource` adapter interface (internal
postings, hiring-partner feeds, ONE licensed job API — evaluate Adzuna vs
JSearch vs Jooble for Indian IT coverage + cost, record an ADR; public
Greenhouse/Lever endpoints; manual/CSV import — **NO raw portal
scraping**); ingestion through the §6.21 JD pipeline (dedupe, freshness
expiry, quality filter); "Jobs for You" feed with per-student relevance +
match badges + gap explanations + daily coach nudge; Apply Assist (tap →
FREE JD-tailored CV → deep link → auto-logged in the tracker with
follow-up reminders); Apply Copilot stubbed behind a feature flag
(human-confirm, Phase 5). Fully autonomous auto-apply stays deferred per
the PRD. Natural trigger: pool eligibility (ADR 0034) activates the feed.


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
