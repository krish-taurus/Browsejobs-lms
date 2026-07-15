# BrowseJobs LMS — Claude Code Build Playbook
### Step-by-step prompts, in order. One prompt = one work session = one commit (or a few).

---

## STEP 0 — Setup (once, ~30 min)

1. Create the project folder and repo:
```
mkdir browsejobs-lms && cd browsejobs-lms && git init
```
2. Copy `browsejobs-lms-requirements.md` into `/docs/` and `CLAUDE.md` into the repo root.
3. Ensure installed: PHP 8.3 + Composer, Node 20+, Docker Desktop (for MySQL/Redis/Mailpit/MinIO). Claude Code setup: see https://docs.claude.com/en/docs/claude-code/overview (you already run it — just `claude` in the project folder).
4. ⚠️ **Hosting reality check**: this stack (Laravel queues + Redis + Next.js + webhooks) will NOT run on Hostinger shared hosting like the PHP site did. You need a VPS (Hostinger VPS / DigitalOcean / Hetzner, 4GB+ RAM to start). Plan this now, not at launch week.

**How to run every session:**
- Start Claude Code in the repo root. For big features, start with plan mode (Shift+Tab) — review the plan before it writes code.
- One prompt below = one session. `/clear` between sessions so context stays sharp.
- After each session: run `composer test` + `npm run typecheck`, skim the diff, commit. Never stack two features on an unreviewed diff.
- If Claude Code asks a question, answer from the PRD; if the PRD is silent, decide and ask it to record an ADR in `/docs/adr/`.

---

## PHASE 1 — Foundation + Funnel + Core LMS (weeks 1–3)

**P1.1 — Scaffold**
> Read CLAUDE.md and docs/browsejobs-lms-requirements.md fully. Scaffold the monorepo exactly per CLAUDE.md: Laravel 11 API in apps/api, Next.js 15 in apps/web, docker-compose (MySQL 8, Redis, Mailpit, MinIO), Pest + Pint + Playwright configured, CI script that runs tests + typecheck. Add .env.example files with placeholder keys only. Verify: docker compose up, both apps boot, example test passes in each.

**P1.2 — Tenancy**
> Implement multi-tenancy per PRD §3: tenants table (branding theme JSON, feature flags, domains, batch numbering pattern), BelongsToTenant trait with global scope, tenant resolution middleware (by domain for public routes, by user for portals). Seed tenant 1 = BrowseJobs with the full design-system theme from CLAUDE.md. Write the cross-tenant denial test pattern we'll reuse everywhere.

**P1.3 — Auth, roles, magic links**
> Build auth per PRD §4: the 8 roles with permissions, staff profiles (description + support-team memberships), student phone/email OTP login, staff email+password+2FA, branded login routes /admin /trainer /student, and the magic-link framework (signed, single-use, action-scoped, expiry per CLAUDE.md) with a consume-and-redirect endpoint. Tests: OTP flow, magic link single-use, role gates, expired links.

**P1.4 — Curriculum + batches**
> Build PRD §6.1 and §6.2 (excluding AI syllabus generator): programs→courses→modules→topics→lessons CRUD in the admin portal; batch management with auto numbering {COURSE}-{YYYYMM}-{seq} + manual override, batch types (masterclass/bootcamp/paid), roster with silo add, bulk multi-select, CSV import, transfer/remove with audit, per-member statuses, capacity guards. Fire TopicCompleted and ModuleCompleted domain events (listeners come later). Seed the 7 BrowseJobs courses with sample curriculum.

**P1.5 — Zoom live classes**
> Build PRD §6.3 core: Zoom Server-to-Server OAuth service (mocked in tests), session scheduling that creates Zoom meetings, gated Join endpoint (enrolment + fee-status check stub), webhook receiver with signature verification for participant joined/left → attendance records (duration %, late flags), and recording.completed → pull to S3(MinIO locally) → recordings table. Trainer attendance override with audit.

**P1.6 — Reminders + cancel/reschedule**
> Build the class notification ladder: queued reminders at 12h, 2h, 5min before every live session containing the magic dashboard join link (channel abstraction: log-driver locally, WhatsApp/email adapters later). Build cancel/reschedule per PRD §6.3: trainer/admin action + reason → Zoom API update/delete → instant batch-wide notifications → session_changes log → reminder ladder cancelled and re-armed for the new time. Tests must prove re-arming works.

**P1.7 — Public site (the showpiece)**
> Read PRD §6.23 and §5 Stage 0–1 plus the CLAUDE.md motion standards first. Build the motion foundation (motion.ts tokens, shared Motion primitives with prefers-reduced-motion, scroll-reveal, animated mono counter, skeleton shimmer), then the full landing page per §6.23's eight-section architecture: hero with ambient premium motion, Proof Engine panel with animated counters + disclaimer, scroll-drawn "Your Journey" timeline, feature showcase sections with animated UI micro-demos of the real product (coach panel, recordings, voice mocks, CV generator, mentor booking, job feed, leaderboard, support SLA), transparent pricing with single/EMI toggle + live schedule preview + included-vs-paid table, green/red promise cards, success wall slot, per-course syllabus lead magnet, FAQ, promise-line footer. Section content CMS-driven (admin-editable, tenant-themed). Plus /courses listing and /courses/[slug] detail with Enroll CTA, masterclass registration page with 12h/2h/5min reminder scheduling, UTM capture → funnel_events. SEO complete (metadata, OG, JSON-LD, sitemap) and CI Lighthouse budget: LCP < 2.5s, CLS < 0.1 on all public pages. This page sells the platform — treat it as the highest-craft artifact in the codebase.

**P1.8 — Student dashboard shell + Recordings tab**
> Build the student portal shell with the CLAUDE.md motion standards: sidebar + mobile bottom tabs + Cmd/Ctrl+K command palette, route fade-slide transitions, skeleton loaders, teaching empty states. Nav: Dashboard, My Classes, Recordings, Tickets placeholder, Profile. Course-suggestion card driven by signup deep-link context, upcoming classes with Join buttons, and the Recordings tab per PRD §6.3 (chronological, module/topic search, watch progress, watermark overlay component, fee-lock state UI). Onboarding: 3-step first-login (profile, goals, DPDP consent capture with purpose list — store consent log) with staggered reveal animation.

✅ **Phase 1 gate:** you can register for a masterclass from an ad link, get reminders, join a Zoom class, see attendance recorded, browse recordings, and admin can create batches and rosters. Demo it end-to-end before Phase 2.

---

## PHASE 2 — Conversion Engine + Money + CRM + Support (weeks 3–5)

**P2.1 — Built-in CRM**
> Build PRD §6.12: leads inbox with all capture sources wired (syllabus, masterclass, enquiry form, CSV, manual; Meta Lead Ads webhook endpoint with signature verification), phone dedupe + merge tool, pipeline kanban of lead_stages with drag-drop and filters, contact timeline aggregating all events, counselor assignment rules (round-robin/by-course), task queue with due dates, speed-to-lead SLA timers. Lead scoring stub (rule-based from attendance/engagement).

**P2.2 — Payments + EMI**
> Build PRD §6.8 checkout: Razorpay integration (test mode; mocked in tests) — single payment and EMI 1/2/3 with the monthly schedule previewed before confirm (instalment 1 now, rest same calendar day monthly, paise integers), personalized payment links (single + bulk from batch roster), UPI AutoPay/eMandate setup for EMI, webhook reconciliation (payment.captured/failed, idempotent), branded GST receipt PDF via the WeasyPrint pipeline, per-student ledger. Enrolment flips Reserved→Enrolled on instalment 1 capture; CRM stage updates automatically.

**P2.3 — Fee ladder + access control**
> Build the escalation ladder per PRD §6.8: scheduled reminders T-7/T-3/T-1/due/daily-grace, dashboard fee widget with live countdown and escalating banners, soft block at grace end (live class join + ENTIRE recordings tab locked, AI tutor stays open), hard block +7d (fee screen only + counselor task), instant webhook-driven unblock. Access checks centralized in one FeeGate service used by Join and Recordings endpoints. Dunning dashboard for admin. Every block/unblock audited.

**P2.4 — Messaging hub**
> Build PRD §6.9: channel adapters (WhatsApp Cloud API, SMTP email, dashboard notifications, web push) behind one Messenger service, template manager with variables and the banned-phrase linter (block "guaranteed job", income claims), Utility-category flag on WhatsApp templates, quiet hours 9pm–9am IST, frequency caps, delivery/read logging, per-student channel preferences. Wire ALL existing notification points (reminders, cancel/reschedule, payment ladder, CRM sequences) through it.

**P2.5 — Bootcamp conversion automation**
> Build PRD §5 Stage 2–3: bootcamp batches link to a target paid batch; on bootcamp completion (or manual per-student trigger) attendees auto-assign to the paid batch as Reserved—Payment Pending and personalized payment links auto-send; non-payer nudge ladder 24h/72h/7d + counselor task at 72h; abandoned checkout recovery links. Funnel dashboard: ad→masterclass→bootcamp→paid conversion by UTM campaign/creative.

**P2.6 — Review-for-voucher**
> Build the engine per PRD §5 Stage 3: post-final-bootcamp-session auto request (magic link) → testimonial form (stars, text, optional video upload, consent-to-publish) → on verified submission auto-issue voucher from the admin-configured rule. Voucher manager admin page: type toggle ₹/%, value, expiry, course scope, usage limits, stacking, redemption analytics. Vouchers pre-apply on payment links. Approved testimonials feed the landing page. Keep vouchers tied to platform testimonials only (Google-review policy guard in the UI copy).

**P2.7 — Student Support Desk**
> Build PRD §6.13 fully: Help & Support area with the 5 categories + Other, ticket creation with attachments, category→team routing map (admin-configurable; Training auto-resolves to the student's batch trainer), round-robin assignment, instant assignee notifications, "Assigned to {name}, {role}" transparency, SLA engine with per-category defaults + 75% warnings + admin escalation on breach, staff ticket workspace (thread, internal notes, canned responses, student-context sidebar), student My Tickets with WhatsApp two-way threading, reopen window, CSAT on resolve. All events → contact timeline + audit log.

**P2.8 — Entitlement engine + self-paced tier**
> Build PRD §6.17 and the self-paced product in §6.3: central Entitlement Service (wallets, quotas, credit transactions, quota_usages) governing metered features; monetization settings admin page (included quotas, pack prices, toggles per tenant — same pattern as voucher manager); in-app one-tap purchase flow via Razorpay with GST receipts and refund workflow; self-paced recorded-course tier (admin flags a completed batch's recordings as a sellable course at configurable % of live price, `self_paced` enrolment type, upgrade-to-live pay-the-difference flow); revenue dashboard by product. Seed the default pricing from the PRD.

✅ **Phase 2 gate:** a bootcamp attendee receives a payment link, pays EMI-2, gets enrolled automatically, misses instalment 2, gets blocked, pays, gets instantly unblocked — with zero manual steps. And a payment concern ticket reaches Accounts within seconds.

---

## PHASE 3 — AI Coach Layer (weeks 5–8)

**P3.1 — AI gateway + telemetry**
> Build the AI service layer per CLAUDE.md (Anthropic API wrapper, versioned prompt files, ai_events cost logging, per-student token budget) and the activity_events telemetry pipeline per PRD §6.4: event ingestion from watch time, logins, quiz attempts, lesson completion. Add Monaco + Judge0 coding labs (self-hosted Judge0 in docker) emitting code telemetry: submissions, run attempts, test pass rates, error patterns, code snapshots.

**P3.2 — Coach Panel + scoring**
> Build mastery map, engagement score, rule-based risk score, and PRI per PRD §6.4, plus the Coach Panel as the dashboard centerpiece: went-well/needs-work (weaknesses visually dominant), exactly ONE Next Best Action, PRI ring with trajectory, streaks, deadlines, fee status. Counselor risk dashboard: daily movers, red flags, AI-suggested intervention scripts.

**P3.3 — AI Tutor (RAG)**
> Build the AI Tutor: tenant-scoped knowledge base from lesson content + transcripts (embed + retrieve), citation-backed answers linking to lessons, hint-ladder on assignments (never full solutions), low-confidence + repeat-question escalation to trainer threads, chat UI in the dashboard. Respect the token budget.

**P3.4 — Assessment automation**
> Build PRD §6.5 automations: AI quiz generator with trainer approval flow; ModuleCompleted → auto email+WhatsApp magic link → timed MCQ page → scores to mastery map → 48h reminder → 96h counselor flag; AI assignment grading against trainer rubric with approve/edit flow; integrity signals (timer, shuffle, tab-focus counter, paste flag, AI-likelihood flag for trainer eyes only); certificate auto-issue with QR verification.

**P3.5 — Content AI + reports**
> Build the recording pipeline: recording.completed → transcript → AI summary, notes, flashcards, draft quiz attached to the lesson (trainer approves). AI Syllabus Generator per PRD §6.2 with WeasyPrint branded PDF, public lead-gated download + dashboard download, regenerate on curriculum change. Weekly student AI report (went well / needs work / focus to crack the final interview) as in-portal + WhatsApp PDF. Trainer pre-class brief (30 min before) and counselor daily digest. Support-desk AI: deflection answers from student data, triage + sentiment queue-jumping, reply drafts, weekly themes report.

**P3.6 — Leaderboards + points**
> Build PRD §6.16: points_events emitted from attendance, quizzes, labs, mocks, streaks, punctuality with admin-configurable weights; batch leaderboard on the student dashboard (weekly + all-time, top-10 named with opt-out, everyone else sees own rank + distance-to-next); badges with batch-feed celebrations; coach integration ("one mock closes the gap to #3"). Anti-gaming: points only from verified events, daily caps.

**P3.7 — Motivation engine + Market Pulse + Content Hub**
> Build PRD §6.18 and §6.19: consented offer-celebration broadcasts (named or anonymous mode) with the personalized "Your Path to the Same" AI guidance card built from each recipient's gap report (3 concrete actions + one-tap mock booking); celebration wall. Market Pulse: curated-feed ingestion → daily AI digest with sources, course-relevance tie-ins, dashboard card + push daily, WhatsApp/email weekly opt-in only. Content Hub: YouTube RSS/API + Instagram Graph + manual podcast entries → dashboard feed + push per release, watch tracking into engagement score. Marketing-category WhatsApp requires explicit opt-in.

✅ **Phase 3 gate:** finish a module → MCQ link arrives on WhatsApp → score updates the Coach Panel → Sunday report tells the student exactly what to fix.

---

## PHASE 4 — Mocks, Mentors, Placement, Whitelabel (weeks 8–10)

**P4.1 — AI mock interviewer core**
> Build PRD §6.6 core (transport-agnostic): role-specific blueprints per course, adaptive follow-up logic, scorecard generation (competency scores, strong/weak moments, model answers, 3 actions) feeding PRI, TopicCompleted → mock prompt card + magic-link nudge (per-topic toggle), progression gate to human mock. Include the optional text-practice mode behind an admin feature flag (off by default).

**P4.2 — Real Interview Intelligence + transcript ingestion**
> Build real_interview_bank with the full ingestion pipeline per PRD §6.6: upload endpoints for Parakeet AI exports, txt/docx/pdf, and audio/video (auto-transcription queued), bulk zip, plus structured debrief forms; AI parsing stage extracting questions/topics/difficulty/round/follow-ups/struggle-points/outcome with dedupe; MANDATORY anonymisation stage (PII scrub + confidential redaction, encrypted originals, uploader consent checkbox logged) before bank entry; placement-officer review queue with batch-approve; bank analytics (question frequency by topic/role/tier, trends over time). Mocks draw from and benchmark against the bank; mock-vs-real gap report per student target role.

**P4.3 — Voice mocks + quota enforcement**
> Wire voice mode via Vapi/Retell (webhook session lifecycle, transcript → the P4.1 scorecard pipeline; mocked in tests) and enforce quotas through the Entitlement Service: included quota per enrolment type (5/course live, 2 self-paced; 1 unlocks per module completed), 10-min session cap with graceful wrap-up, out-of-credits → one-tap top-up purchase (₹249/1, ₹599/3 defaults from monetization settings). Per-session cost logging into ai_events for margin tracking.

**P4.4 — Mentor scheduling (native)**
> Build PRD §6.11 fully native: mentor profiles + expertise tags, recurring availability + exceptions (IST) + optional Google Calendar busy-sync, student combined-availability calendar with expertise filter, booking → Zoom auto-create → instant both-side notifications + .ics → T-24h/T-1h reminders, 4h reschedule/cancel rule, no-show tracking, post-session mentor feedback → PRI, ratings. Coach recommends bookings on persistent weakness. Placement interviews reuse this engine.

**P4.5 — CV generator + placement**
> Build PRD §6.7 and §6.11 placement: CourseCompleted → auto-generation of the comprehensive CV (credit-free, celebration notification); ATS suite (parse simulation score, JD keyword match, format lint, quantified bullets); 3 free generations then ₹99/3-pack via the Entitlement Service; branded WeasyPrint templates; placement-officer approval; versioning + share links. Placement: pool gating (PRI + CV + human mock), job board, application pipeline, debrief capture after every real round, offer tracking → Proof Engine aggregates + pay-after-placement fee milestone + offer-celebration trigger (consent-gated). End-of-course comprehensive report.

**P4.6 — Review protection + job-probability boosters**
> Build PRD §6.20: NPS pulses at week-1/mid/pre-placement with promoter→Google-review routing (unconditioned) and detractor→instant counselor rescue; rage-signal detection (failed payments, low CSATs, engagement cliffs, angry sentiment) firing priority interventions; pause/defer enrolment workflow with admin rules; week-1 white-glove onboarding checklist + call task; LinkedIn profile optimizer; GitHub portfolio auto-builder from lab projects; application tracker with AI tailoring; interview-day prep packs from the real-interview bank. Career+ subscription product (₹499/mo default) on the Entitlement Service.

**P4.7 — Curriculum Intelligence + Advice Graph**
> Build PRD §6.21: job-postings ingestion (CSV/manual JD import → AI skill extraction → demand trends), syllabus recommendation engine cross-referencing interview-bank frequencies + JD demand + debrief failure points → evidence-linked Syllabus Recommendation Reports per course (quarterly scheduled + on-demand) → trainer/admin approval → curriculum version applied → syllabus PDFs auto-regenerate (next batches only). Add hiring-partner feedback forms, salary_benchmarks admin dataset, alumni 6/12-month check-in automation, and PRI weight calibration from placement outcome correlations. Coach advice upgraded to evidence-backed claims from anonymised aggregates only — write the aggregation privacy test.

**P4.8 — Live Job Feed + Apply Assist**
> Build PRD §6.22: JobFeedSource adapter interface with implementations for internal placement postings, hiring-partner feeds, ONE licensed job API (evaluate Adzuna vs JSearch vs Jooble for Indian IT coverage + cost; record an ADR), public ATS endpoints (Greenhouse/Lever), and manual/CSV import — NO raw portal scraping. Ingestion through the 6.21 JD pipeline with dedupe, freshness expiry, quality filtering. "Jobs for You" dashboard feed with per-student relevance scoring, match-percentage badges with gap explanations, save/dismiss, daily coach nudges. Apply Assist: tap Apply → free JD-tailored CV for this application → deep link to source posting → auto-logged in the application tracker with follow-up reminders and outcome capture into the Advice Graph. Stub the Apply Copilot interface (human-confirm ATS pre-fill) as a feature-flagged Phase 5 extension point.

**P4.9 — Whitelabel + launch prep**
> Build tenant theming UI (logo, colors, domain), feature-flag admin, tenant provisioning flow for super-admin. Then run the full PRD §10 checklist as an audit: produce a written report of every unchecked item with fixes. Load-test targets per §10. Build the DPDP data-access/deletion request workflows.

**P4.10 — Cutover**
> Prepare the browsejobs.ai replacement: crawl the current PHP site, produce a URL map with 301 redirects, verify SEO parity (titles, metas, schema), staging sign-off checklist, DNS cutover runbook with rollback plan.

✅ **Launch gate:** §10 checklist 100% green, keys rotated, staging demo of the full journey — ad link to placement — witnessed end-to-end.

---

## Working Rhythm (repeat every session)
1. `claude` in repo root → plan mode for anything non-trivial → review plan → approve.
2. Let it build → run `composer test` + `npm run typecheck` → skim diff.
3. Anything unclear → it must cite the PRD section it implemented.
4. Commit with the prompt ID (e.g., `feat(P2.3): fee ladder + access control`).
5. `/clear`, next prompt.
