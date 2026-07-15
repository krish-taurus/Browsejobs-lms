# BrowseJobs LMS — Product Requirements Document (PRD)
### AI-Driven, Whitelabel-Ready Unified Learning Platform
**Version 1.8 · July 2026 · IBrowseJobs Technologies Pvt Ltd**

---

## 1. Vision

One unified platform owning the ENTIRE candidate lifecycle: **Ad click → Masterclass → Free Bootcamp → Paid Batch → Learning → AI Coaching → Mock Interviews → Mentor Sessions → Placement.** An AI Learning Engine acts as each candidate's personal coach — monitoring everything (code written, attendance, quizzes, mocks), hyperfocusing on weaknesses, and never letting them deviate from the path to their final interview. Multi-tenant from day one for whitelabeling.

**Design law #1 — One-Tap Principle:** every message the student ever receives deep-links (magic link) to exactly the right page, already logged in. The dashboard always shows exactly ONE primary next action. Zero hunting, zero friction, from first ad click to placement.

**Design law #2 — AI does the monitoring, drafting, grading, and nudging. Humans (trainers, mentors, counselors) do the teaching, verifying, and relationship-building.**

**Confirmed decisions (v1.3):**
1. This unified app **REPLACES the current PHP browsejobs.ai site**. Cutover plan: build → staging → content/SEO parity check → DNS switch on browsejobs.ai with 301 redirects from every old URL → browsejobs.in continues redirecting to primary.
2. **Mentor/interview scheduling is built natively** — own availability + booking engine, zero third-party scheduling subscription costs.
3. **Education CRM is built INTO this platform** (leads, pipeline, counselor workflows). The legacy Laravel system keeps only internal ops (HRM: staff attendance/leave, internal chat, Instagram/Gmail/social tools) with a thin API where needed. See 6.12.

---

## 2. Tech Stack (confirm before build)

| Layer | Choice | Rationale |
|---|---|---|
| Backend | Laravel 11 (PHP 8.3), REST API | Matches existing BrowseJobs CRM stack |
| Frontend | Next.js 15 + TypeScript + Tailwind — public site AND all portals | One codebase, one design system, unified funnel |
| Database | MySQL 8 (single DB, row-level tenancy) | Matches CRM |
| Cache/Queues | Redis + Laravel Horizon | All automation on queues |
| LLM | Anthropic API (Claude) | Already in use |
| Voice AI | Vapi / Retell AI | Already in use |
| Live classes | Zoom Server-to-Server OAuth (Meetings + Webinars + Webhooks + Cloud Recording) | Webinars for masterclasses, Meetings for classes |
| Payments | Razorpay (Orders, Payment Links, Subscriptions, UPI AutoPay) | Existing integration |
| Scheduling | Native availability + booking engine (own build — no third-party scheduling costs); optional Google Calendar sync | In-dashboard, whitelabel-safe |
| Messaging | WhatsApp Cloud API, SMTP/SES email, Web Push | |
| Storage | S3-compatible | |
| PDF generation | WeasyPrint pipeline (bj-document-system.css + build.py) | Branded syllabi/reports/CVs |

**Non-negotiables:** secrets in `.env` only; ALL API keys rotated before go-live; every AI/messaging job queued; audit log on every sensitive action.

---

## 3. Multi-Tenancy & Whitelabel Architecture

- `tenants`: name, slug, custom domain, branding theme (JSON), feature flags, plan, status. BrowseJobs = tenant 1.
- `tenant_id` on every model, enforced by global scope; cross-tenant access must fail in tests.
- Per-tenant: landing/course pages, email identity, WABA, Razorpay account, Zoom credentials, batch numbering pattern, document templates.
- Theming via CSS variables (default = BrowseJobs design system: ink navy #0A1220, trust blue #1B6DF0, verify green #0BA860, Sora/Inter/IBM Plex Mono).

---

## 4. Roles & Authentication

Roles: **Super Admin, Admin (Institute), Trainer, Mentor, Counselor, Placement Officer, Support Agent, Student.**
- Every staff user has a **profile with description, role(s), and support-team memberships** (which concern categories they handle) — this powers the ticket routing engine (6.13) and the "who's who" directory students see when raising concerns.
- Branded logins: `/admin`, `/trainer`, `/student` (single auth service, role-routed).
- Students: phone/email + OTP. Staff: email + password + 2FA.
- **Magic links everywhere**: single-use, short-expiry, action-scoped links in every WhatsApp/email that auto-authenticate and land the student on the exact page (join class, take MCQ, pay instalment, leave review).
- Device tracking, single-active-session option for students, rate limiting, login audit.

---

## 5. THE JOURNEY — Lead to Deployment, Step by Step (master spec)

Every stage below is a state in the `lead_stages` pipeline, visible in admin as a funnel board, synced to CRM.

### Stage 0 — Ad → Landing
- Meta ad click lands on `/` or course page; UTM params captured to `funnel_events` (creative-level conversion tracking for your Meta campaigns).
- CTAs: "Register for Free Masterclass" (primary), "Download Syllabus" (lead magnet — captures name + phone → creates lead → nurture sequence), "Enroll Now" (direct).

### Stage 1 — Masterclass (free live event)
- Public masterclass page with date/time; registration = name + phone + email (no password — account auto-created in `lead` status).
- Instant WhatsApp + email confirmation with calendar invite.
- Auto reminders **12h, 2h, 5min** before, each with a magic "Join" link (Zoom Webinar; no login friction).
- Attendance tracked via Zoom webhooks → attended/no-show segments for follow-up automation.

### Stage 2 — Free Bootcamp
- Bootcamp = a **free batch type** in the same engine — same live classes, recordings tab, quizzes, reminders. Students experience the real platform before paying (the product sells itself).
- Masterclass attendees auto-invited; registration = one tap (account already exists).
- Engagement telemetry during bootcamp (attendance, quiz scores, questions asked) → **AI lead score** shown to counselors: who's hot, who needs a call.

### Stage 3 — Conversion: Bootcamp → Paid Batch
- Admin links each bootcamp to its target paid batch. On bootcamp completion (or per-student, anytime):
  - Attendees are **auto-assigned to the paid batch in `Reserved — Payment Pending` status**.
  - **Personalized payment links auto-sent** (WhatsApp + email): student's name, course, batch start date, single/EMI (1-2-3) options, any voucher pre-applied. Link → checkout → paid → status flips to `Enrolled` automatically.
- **Review-for-Voucher engine**: after the final bootcamp session, auto-request feedback + testimonial (text/video upload, star rating, consent-to-publish checkbox). Verified submission → **voucher auto-issued** — fully admin-controlled from the settings page: type (flat ₹ OR percentage), value, expiry window, per-course applicability, usage limits, stacking rules — and pre-applied to their payment link. Approved testimonials feed the landing page social proof.
  - ⚠️ Compliance guard: tie the voucher to the **platform testimonial/feedback**, not to Google reviews — Google prohibits incentivized reviews and can wipe your listing. Ask for the Google review separately, unconditioned.
- Non-payers: nudge ladder (24h/72h/7d), counselor task after 72h, batch-start-scarcity messaging ("Batch DE-202608 starts Monday, 4 seats left").

### Stage 4 — Enrolled: Learning
- First login → 3-step onboarding (profile, goals, consent) → Candidate Dashboard with Coach Panel.
- Daily loop: dashboard shows ONE next action → attend class → auto MCQ after module → auto mock prompt after topic → weekly AI report.

### Stage 5 — Placement-Ready → Deployed
- PRI threshold + approved CV + cleared human mock → placement pool → interviews scheduled via calendar engine → real-interview debriefs → offer → **Deployed** 🎉 (feeds Proof Engine stats; triggers pay-after-placement milestone where applicable).

---

## 6. Module Specifications

### 6.1 Batch Management (expanded)
- **Batch numbering**: auto-generated per tenant pattern `{COURSE}-{YYYYMM}-{seq}` (e.g., `DE-202608-01`) **with manual override** at creation — both options, uniqueness enforced.
- Batch types: `masterclass`, `bootcamp` (free), `paid`. Fields: course, trainer(s), schedule, capacity, start/end, linked bootcamp/source.
- **Roster allocation — three ways:**
  1. **Silo**: add one student (search existing or create new external person → account + optional payment link fired instantly).
  2. **Bulk**: multi-select from bootcamp attendee list / lead segments, or CSV import (name, phone, email) → accounts created → assigned → payment links batch-sent.
  3. **Auto**: bootcamp completion pipeline (Stage 3 above).
- Remove / transfer students between batches with reason + full audit trail; capacity guards; per-member status (`reserved`, `payment_pending`, `enrolled`, `dropped`, `transferred`, `completed`).
- Bulk actions on any roster selection: send payment links, send announcement, move batch, export.

### 6.2 Course & Curriculum
- Programs → Courses → Modules → Topics → Lessons (live class, video, notes, quiz, coding lab, assignment, project, mock milestone).
- Unlock rules: sequential / date / performance-gated / fee-gated. `TopicCompleted` and `ModuleCompleted` are first-class events driving automations.
- **AI Syllabus Generator**: for each course AND each segment/module, AI generates a syllabus document from curriculum data (outcomes, topics, tools, projects, weekly plan) → trainer approves → rendered as branded PDF (WeasyPrint) → downloadable from the public course page (lead-gated: name+phone) and from the student dashboard. Auto-regenerates when curriculum version changes.
- Curriculum versioning, prerequisites, resource library.

### 6.3 Live Classes & Recordings — Zoom
- Sessions auto-create Zoom meetings; raw links never exposed.
- **Class reminders — 12h, 2h, 5min before every live class** — WhatsApp + email + push + dashboard, each containing the magic dashboard link to log in and join. Timings configurable per tenant; defaults per Krish spec.
- **Cancel / Reschedule (trainer or admin):**
  - Cancel: pick reason → Zoom meeting deleted → **every student in the batch auto-notified instantly** (WhatsApp + email + dashboard banner + push) → optional make-up session suggestion → calendar invites cancelled.
  - Reschedule: pick new date/time → Zoom meeting updated via API → batch auto-notified with old vs new time → calendar invites updated (.ics) → **reminder ladder re-armed for the new time automatically**.
  - All changes logged in `session_changes` with actor + reason.
- Join gating by enrolment + fee status; Zoom webhooks → auto attendance (duration %, late flags, trainer override).
- **Recordings tab**: chronological, searchable by module/topic, watch-progress tracked, watermarked, no downloads. Fee soft-block locks ALL recordings (past + future); payment → instant restore.
- **Self-Paced Recorded Tier (revenue product)**: admin can flag any completed batch's recording set as a **sellable recorded course** — priced lower than live (default 50% of live fee, admin-configurable), one click to publish. Self-paced buyers get: recordings, notes, quizzes, AI tutor, MCQs, and quota'd mocks — but no live classes or batch trainer access. Upgrade path: pay the difference anytime to join the next live batch (upsell nudge built into their coach panel). Separate enrolment type `self_paced`; same fee gating and EMI options apply.

### 6.4 AI Learning Engine — The Personal Coach
- **Telemetry**: code metrics from browser labs (lines, submissions, run attempts, test pass rates, error patterns, code snapshots), watch time, streaks, quiz trends, mocks taken, mentor sessions, forum activity → `activity_events` stream.
- Per-student: **Mastery map**, **Engagement score**, **Risk score** (dropout + placement), **Placement Readiness Index (PRI)**.
- **Coach Panel (dashboard centerpiece)**: went-well / needs-work (weaknesses visually dominant), exactly ONE Next Best Action, PRI ring + trajectory, streaks, deadlines, fee status.
- AI Tutor (RAG over course content + transcripts, citations, hint-ladder, trainer escalation); adaptive remediation with spaced repetition (day 2/7/21); weekly AI study plan.

### 6.5 Assessments & Auto-MCQ Dispatch
- Quiz builder + AI quiz generator (trainer-approved). Coding labs: Monaco + Judge0 (Python, SQL, Bash, JS) — the code-telemetry source.
- **`ModuleCompleted` → auto email + WhatsApp with magic link → timed MCQ page.** Scores feed mastery map; 48h non-completion reminder; 96h counselor flag.
- AI grades assignments against trainer rubric → trainer approves → student receives.
- Integrity: timers, shuffling, tab-focus counter, paste flags, AI-content likelihood (trainer review only). Certificates auto-issued, branded, QR-verified.

### 6.6 AI Mock Interviewer + Real Interview Intelligence
- **`TopicCompleted` → auto mock prompt** (dashboard card + WhatsApp/email magic link into the mock room). Configurable per topic.
- **Voice-only AI mocks (Vapi/Retell), quota-governed** — no unmetered token burn. Every session draws from the entitlement engine (6.17): default included quota = 5 voice mocks per paid course (1 unlocked per module completed — quota doubles as a progression reward), 10-minute default session length, hard cut with graceful wrap-up. Top-ups sold in-app: ₹249/session or ₹599/3-pack (admin-configurable like vouchers). Self-paced tier includes 2. Optional admin toggle: unlimited *text* practice mode (near-zero cost) as a free layer beneath voice — recommended, off by default per founder preference.
- Human mock with placement officer remains quota-free (it's a human, not tokens).
- Role-specific blueprints per course; adaptive follow-ups; scorecard (per-competency, strong/weak moments, model answers, 3 actions) → PRI.
- **Real Interview Intelligence**: placement team curates `real_interview_bank` (actual questions/rounds from candidates' real interviews, anonymised, tagged). Mocks drawn from and benchmarked against it. **Mock-vs-Real gap report**: "Real DE interviews weight SQL optimisation 30% — you're at 55%. Close this to crack your final interview." Every real interview debrief enriches the bank + updates the student's report.
- **Transcript Ingestion Pipeline (NEW)** — feed the bank at scale with real interview scripts:
  - **Sources**: Parakeet AI exports (file drop or API import when credentials available), manual upload (txt/docx/pdf), audio/video upload (auto-transcribed), bulk zip upload, and the existing structured debrief forms. Whatever works — the pipeline is source-agnostic.
  - **AI parsing stage**: each transcript is parsed into structured records — questions asked (verbatim + normalised), topic tags, difficulty, round type, follow-up chains, what strong answers looked like, where the candidate struggled, outcome. Dedupe against existing bank entries.
  - **Mandatory anonymisation stage**: PII scrub (candidate + interviewer names, contact details) and company-confidential redaction before anything enters the bank; original files stored encrypted with restricted access; uploader confirms they have the right to share the recording/transcript (consent checkbox, logged). Non-negotiable — interview recordings are legally sensitive.
  - **Review queue**: placement officer approves parsed extractions before they go live in the bank; batch-approve for high-confidence parses.
  - Bank analytics: question frequency by topic/role/company-tier, trending topics over time — this feeds Curriculum Intelligence (6.21) so the syllabus tracks the real market.
- Progression: AI mock threshold → human mock → placement pool.

### 6.7 AI CV Generator
- **Auto-generation on course completion**: the moment `CourseCompleted` fires, the system builds the student's most comprehensive CV automatically — profile, every skill from completed modules, projects with metrics from labs, certifications, mock-interview strengths — and drops it in their dashboard with a celebration notification. This first CV does NOT consume a credit.
- **ATS-cracking suite**: ATS-parse simulation score, keyword-match against pasted JD, action-verb + quantified-impact bullet rewriting, format lint (no tables/graphics that break parsers), role-specific keyword libraries per course.
- **Credit model (freemium)**: 3 free generations per student (a generation = a new AI build or JD-tailored version; manual text edits are free). Beyond that: **₹99 per 3-generation pack**, purchased in-app via Razorpay, admin-configurable pricing. Credits tracked by the entitlement engine (6.17).
- Branded PDF templates (per-tenant), placement-officer approval before placement use, versioning + share links.

### 6.8 Fee Management, EMI Engine & Access Control
- Plans: single, or EMI **1/2/3 monthly instalments** (instalment 1 at checkout, rest same calendar day monthly — schedule previewed before confirm). Pay-after-placement milestones. **Voucher/coupon manager (admin page)**: create/edit vouchers with type toggle (flat ₹ / %), value, expiry, course scope, usage limits, stacking rules; redemption analytics; compliant strike pricing.
- Razorpay: checkout, **personalized payment links** (funnel Stage 3 + admin bulk-send), UPI AutoPay/eMandate for EMI auto-debit, webhook reconciliation, branded GST receipts.
- **Dashboard-native awareness**: fee widget with live countdown ("₹15,000 due in 6 days"), escalating banners (info → warning → urgent → red overdue with one-tap pay).
- **Escalation ladder** (configurable): T-7d/T-3d/T-1d reminders → due day → daily grace reminders (default 5 days) → **soft block** (live classes + entire Recordings tab locked; AI tutor stays open) → +7d **hard block** (fee screen only, counselor call task) → payment captured → **instant auto-unblock**.
- Dunning dashboard: aging, expected collections, failed auto-debit retries.

### 6.9 Messaging & Automation Hub
- Channels: WhatsApp Cloud API (primary), email, dashboard notifications, push.
- Journey library: masterclass confirmations + 12h/2h/5min reminders, bootcamp invites, payment links + nudges, review-for-voucher requests, class reminders (12h/2h/5min), cancel/reschedule alerts, absence follow-ups with recording link, MCQ dispatch, mock nudges, fee ladder, weekly reports, inactivity win-back, mentor confirmations.
- **WhatsApp templates = pre-approved Utility category only; banned-phrase linter (no "guaranteed job", no income claims)** — protects the WABA.
- Quiet hours (9pm–9am IST), frequency caps, channel preferences, delivery/read logs. AI-personalised bodies within approved frames.

### 6.10 Auto-Reporting & Analytics
- Student weekly AI report (went well / needs work / focus on X to crack the final interview) — in-portal + WhatsApp PDF.
- End-of-course comprehensive report: full journey, every score, code trajectory, mock-vs-real gaps, readiness verdict + interview strategy.
- Counselor daily digest (risk movers + scripts); trainer pre-class AI brief; post-class AI summary (transcript → notes, flashcards, quiz draft).
- **Funnel analytics**: ad → masterclass → bootcamp → paid conversion by campaign/creative (UTM-level), review/voucher redemption rates, batch fill rates.
- Admin: batch health, collections, trainer performance, cohort completion, Proof Engine aggregates.

### 6.11 Mentor & Interview Scheduling
- Mentor profiles (expertise tags, bio, rating); recurring availability + exceptions (IST); optional Google Calendar sync.
- Student books from **combined availability calendar** of mentors supporting their course → slot → purpose → confirm → Zoom auto-created → **both sides instantly notified** (WhatsApp + email + dashboard + .ics) → T-24h/T-1h reminders.
- Reschedule/cancel rules (4h notice), no-show tracking, post-session mentor feedback → PRI, student rating.
- AI coach proactively recommends bookings on persistent weakness. Placement interviews use the same engine.

### 6.12 Built-in Education CRM (native module — NEW)
The full student/lead lifecycle CRM lives INSIDE this platform. No external CRM required for the education funnel — a whitelabel selling point ("CRM + LMS in one").

- **Lead capture from every source into one inbox**: syllabus downloads, masterclass registrations, bootcamp signups, website enquiry forms, **Meta Lead Ads webhook (instant, no Zapier)**, WhatsApp inbound, manual entry, CSV import. Dedupe by phone number; merge tool for duplicates.
- **Pipeline board**: the `lead_stages` funnel (Lead → Masterclass Registered → Attended → Bootcamp → Reserved/Payment Pending → Enrolled → Active → Placement-Ready → Placed) as a drag-drop kanban with filters by course, batch, source, campaign, counselor.
- **Contact timeline**: every touchpoint on one screen — messages sent/read, classes attended, payments, calls, notes, tasks.
- **Counselor workflows**: assignment rules (round-robin / by course), task queue with due dates, follow-up sequences (auto-pause on reply), speed-to-lead SLA timers and alerts.
- **Call integration**: outbound AI voice agent (Vapi/Retell) triggered from the lead card; call recordings + transcripts + AI sentiment logged to the timeline; click-to-call for counselors.
- **Lead scoring**: AI score from engagement telemetry (masterclass/bootcamp attendance, quiz activity, link clicks) — counselors see who's hot.
- Reporting: source/campaign ROI (UTM-level), counselor conversion rates, stage velocity, drop-off analysis.

**Legacy system boundary (BrowseJobsBackendLaravel)**: keeps internal ops only — staff HRM (attendance, leave approvals), internal chat, Instagram/Gmail/social analytics tools. Thin API + HMAC-signed webhooks between the two ONLY where needed (e.g., staff records, TaurusAI sync). `CrmConnector` interface retained so whitelabel tenants who insist on their own CRM (HubSpot/Zoho) can plug in via adapter — optional, never required.

### 6.13 Student Support Desk — Concerns & Ticketing (NEW)
A **Help & Support** area in the student dashboard where every concern reaches the right team immediately.

- **Raise a concern**: student picks a category — **Payments · Technical · Mentorship · Training · Interview Prep** (+ Other) — adds description, attachments (screenshots), and optional urgency. Ticket ID issued instantly with acknowledgment.
- **AI first-response (deflection layer)**: before the ticket is created, AI offers an instant answer from the knowledge base + the student's own data ("Your next EMI is ₹15,000 due 5 Aug — pay here"). Student accepts or proceeds to raise the ticket anyway. Typically deflects 30–50% of volume.
- **Category → Team auto-routing (admin-configurable mapping)**:
  | Category | Routed to |
  |---|---|
  | Payments | Accounts/Admin team |
  | Technical | Support Agents |
  | Mentorship | Counselors / mentor coordinator |
  | Training | The student's own batch Trainer (auto-resolved from enrolment) |
  | Interview Prep | Placement Officers |
  Assignment within a team: round-robin or load-based; manual reassignment allowed; multi-role staff supported via team memberships.
- **Instant notifications**: the moment a ticket lands, the assigned staff member is pinged on WhatsApp + email + dashboard; the student sees "Assigned to {name}, {role}" for transparency.
- **SLA engine**: per-category first-response and resolution targets (defaults: Payments 2h/24h, Technical 4h/48h, Training 4h/24h, Mentorship 8h/48h, Interview Prep 8h/48h — all editable in admin). Breach warnings to assignee at 75% of SLA; **auto-escalation to Admin on breach**.
- **Staff ticket workspace**: threaded conversation, internal notes, canned responses, **AI-drafted reply suggestions**, and a student-context sidebar (batch, fee status, attendance, risk score, recent activity) so staff never have to ask "which batch are you in?". Status flow: Open → In Progress → Waiting on Student → Resolved → Closed.
- **Student side**: My Tickets tab with live statuses; replies work from the dashboard OR by replying on WhatsApp (two-way threading); reopen within 7 days; CSAT rating on resolution.
- **AI triage**: auto-category suggestion, urgency/sentiment detection (an angry payment dispute jumps the queue), duplicate detection, and a weekly "top concern themes" report to admin — fix root causes, not just tickets.
- Every ticket event lands on the student's CRM contact timeline and the audit log.

### 6.14 Admin, Settings & Compliance
- Tenant settings: branding, credentials, batch numbering pattern, reminder timings, fee ladder, quiet hours, feature flags, automation toggles.
- Full audit log. **DPDP Act 2023**: consent at signup (incl. activity-monitoring telemetry disclosure), access/deletion workflows, retention policy, breach runbook — launch blocker.
- Backups nightly + restore runbook.

### 6.15 Phase-4 Nice-to-Haves
Peer groups, AI-moderated forum, PWA offline notes, multi-language tutor (English/Tamil/Hindi), trainer marketplace, SCORM import, alumni network.

### 6.16 Batch Leaderboards & Momentum (NEW — core)
- **Batch leaderboard on every student dashboard**: ranked by a transparent Prep Score = weighted points from attendance, quiz scores, lab submissions, mocks taken, streaks, assignment punctuality (weights admin-configurable). Weekly reset view + all-time view.
- Anti-toxicity by design: top 10 shown by name (opt-out available), everyone else sees own rank + "distance to next rank" — motivating, never humiliating. Badges for milestones (First Mock Done, 7-Day Streak, Module Ace), celebrated in batch feed.
- Points events feed the leaderboard AND the coach ("You're 40 points from #3 — one mock closes the gap").

### 6.17 Freemium & Revenue Engine (NEW — platform primitive)
One central **Entitlement Service** governs every metered feature — the whitelabel monetization backbone:
- **Wallets & quotas**: CV credits, voice-mock sessions/minutes, mentor-session allowances, per-student AI token budgets. Included quotas per enrolment type (live / self-paced), top-ups purchasable in-app via Razorpay.
- **Monetization settings page (admin)**: set included quotas, pack prices, and toggles per feature per tenant — same pattern as the voucher manager. Defaults: CV ₹99/3-pack, voice mock ₹249 or ₹599/3, self-paced tier 50% of live, extra mentor 1:1 ₹499.
- **Revenue surfaces**: self-paced recorded courses (6.3), CV packs (6.7), voice-mock packs (6.6), extra mentor sessions (6.11), optional post-course **Career+ subscription** (₹499/mo: continued AI coach + market pulse + monthly mock + CV refresh — retention revenue after placement prep ends).
- **Freemium guardrail (protects reviews + the pay-after-placement brand)**: everything essential to the placement promise stays included — classes, recordings (for paying students), AI tutor, MCQs, first comprehensive CV, base mock quota, support. Charges apply only to *extras beyond generous included quotas*. Paywalling essentials is how ed-tech companies earn 1-star reviews; we will not.
- Purchase UX: one-tap buy inside the moment of need ("Out of mock credits — get 3 more for ₹599"), GST receipts, refund workflow, revenue dashboard by product.

### 6.18 Motivation Engine — Offer Celebrations & Gap Guidance (NEW)
- **Offer celebration broadcast**: when a student's offer is confirmed (placement module), batch-mates + course-mates receive a celebration notification: "🎉 {Name} just received an offer as {Role}!" — **only with the placed student's explicit consent** (toggle at debrief; anonymous mode available: "A Data Engineering student from batch DE-202605...").
- **Personalized 'Your Path to the Same' attachment**: the notification links each recipient to an AI-generated guidance card built from THEIR mastery map and gap report: "{Name} scored 85%+ on SQL optimisation — you're at 55%. Fix this with: {3 concrete actions, resources, and a booked-in-one-tap mock}." Concrete tips and tricks, never generic cheerleading.
- Frequency-capped and quiet-hours-respecting; celebration wall on the dashboard; feeds the Proof Engine aggregates.

### 6.19 Market Pulse & Content Hub (NEW)
- **Daily Market Pulse**: AI-curated daily digest of the IT job market — what's booming, what's cooling, in-demand skills, notable hiring/layoff news — sourced from curated feeds, summarised by AI with source links, tied back to their course ("Kafka mentions in DE job posts up — you cover this in Module 4"). Delivery: daily dashboard card + push; **WhatsApp/email digest weekly, opt-in only** (daily WhatsApp promo = churn + WABA risk).
- **Content Hub**: auto-detects new releases — The Offer Letter Podcast episodes, YouTube videos (RSS/API), Instagram posts (Graph API, already integrated in legacy stack) — into a dashboard feed with push notification per release: "New episode: {guest} on cracking {topic} interviews." Watch/listen tracking feeds engagement score. Keeps students inside the BrowseJobs content universe = loyal viewers + retention.
- All promotional-category WhatsApp messages require explicit Marketing opt-in (WABA protection); in-app + push are the default channels.

### 6.20 Service Quality & Review Protection (NEW — recommended suite)
Features engineered to prevent negative reviews and raise job probability:
- **NPS pulse surveys** at key milestones (week 1, mid-course, pre-placement): score 9–10 → gently routed to leave a public Google review (unconditioned, policy-safe); score ≤6 → **detractor rescue**: instant counselor task + admin alert BEFORE frustration goes public.
- **Rage-signal detection**: AI watches for churn precursors — repeated failed payments, 2+ low CSAT tickets, sharp engagement drop, angry sentiment in messages — and fires a priority human intervention.
- **Pause/defer instead of drop**: students hitting life events can freeze enrolment and rejoin a later batch (rules + limits admin-set). The single biggest refund-dispute and bad-review killer in ed-tech.
- **Week-1 white-glove onboarding**: guaranteed counselor welcome call + platform tour; first-week check-in survey.
- **Job-probability boosters**: AI **LinkedIn profile optimizer** (headline, about, skills audit vs target role), **GitHub portfolio auto-builder** (turns their lab projects into a clean public portfolio with READMEs), application tracker with AI-suggested tailoring per application, and interview-day prep checklists (research pack on the company, likely questions from the real-interview bank).
- Public testimonial pipeline stays consent-based and platform-first (6.12 review engine).

### 6.21 Curriculum Intelligence & The Advice Graph (NEW)
The platform's data flywheel: every signal feeds ONE intelligence layer that powers coaching advice, gap reports, syllabus recommendations, and PRI calibration.

**Market-driven syllabus redefinition:**
- AI continuously cross-references: (a) real-interview-bank question frequencies and trends, (b) job-posting skill demand, (c) student debriefs showing WHERE our candidates actually fail in real interviews, (d) market pulse signals.
- Output: **Syllabus Recommendation Reports** per course (quarterly + on-demand): "Add {topic} — appears in 42% of real DE interviews, currently absent from Module 4. Expand {topic}. Deprioritise {topic} — down 60% in job postings." Every recommendation carries its evidence (linked transcripts, JD counts).
- **Trainer/admin approval flow** → accepted changes apply through curriculum versioning → AI syllabus PDFs regenerate automatically → running batches unaffected, next batches get the updated syllabus. The syllabus literally tracks the market.

**Data sources — current + recommended additions (the "seek any other data" answer):**
| Source | Status | What it unlocks |
|---|---|---|
| Mock interview scorecards | ✅ built | Competency baselines |
| Student telemetry (code, watch, quizzes) | ✅ built | Mastery maps, risk, coaching |
| Real interview transcripts (Parakeet/manual) | ✅ this version | Market-true question bank, syllabus signals |
| Interview debriefs + outcomes | ✅ built | Failure-point analysis |
| **Job postings ingestion** | ➕ add | CSV/manual import of JDs (Naukri/LinkedIn/Indeed exports) → AI extracts skill frequencies → demand trends per role. Feeds syllabus engine + JD-tailored CVs + Market Pulse |
| **Placement outcome correlations** | ➕ add | Which scores/skills/behaviours actually correlate with offers → auto-calibrates PRI weights and coach advice with evidence, not opinion |
| **Hiring partner feedback** | ➕ add | Structured post-interview feedback form for companies interviewing BrowseJobs candidates → the sharpest possible gap signal |
| **Salary benchmarks** | ➕ add | Offer-evaluation and negotiation guidance per role/city/experience (curated dataset, updated quarterly) |
| **Alumni career tracking** | ➕ add | 6/12-month post-placement check-ins → long-term outcome data, proof stats, referral pipeline |
| Student aspirations (target role/companies) | ✅ onboarding | Personalised targeting of all of the above |

- The Coach consumes the full graph: advice like "Students with your profile who practised {X} 3+ times got offers 2.1× more often" — evidence-backed guidance, not generic tips.
- All cross-student learning uses anonymised aggregates only (DPDP-consistent); per-student data never leaks into another student's view.

### 6.22 Live Job Feed & Apply Assist (NEW)
Open positions on every student's dashboard, matched to them, with their platform CV one tap away.

- **Aggregation (compliant, pluggable sources — NOT raw portal scraping)**: internal placement postings + hiring-partner feeds (priority-flagged), licensed job APIs (Adzuna / JSearch / Jooble — pick one at build time by coverage of Indian IT roles and cost), public ATS endpoints (Greenhouse, Lever, Workable), and manual/CSV import by the placement team. Raw scraping of LinkedIn/Naukri is explicitly out of scope: ToS violations, IP blocks, fragility, and legal risk — the API route delivers the same postings safely. Sources are adapters behind one `JobFeedSource` interface so new sources plug in later.
- **Ingestion → the same JD pipeline (6.21)**: dedupe, AI skill/role/location/experience extraction, freshness tracking (stale postings auto-expire), spam/low-quality filtering.
- **"Jobs for You" dashboard feed**: relevance-scored per student (target role + mastery map + location + experience), match-percentage badge with WHY ("matches your Python, Airflow, SQL — gap: Kafka"), save/dismiss, freshness sort, daily coach nudge ("3 new Data Engineer roles match you today"). Feed data also enriches Market Pulse.
- **Apply Assist flow**: tap Apply → platform auto-tailors their CV to THIS job's JD (one free JD-tailored version per application — applying is the outcome we want, we don't tax it; standalone generations still follow 6.7 credits) → CV download/copy ready → deep link opens the original posting on the source portal → application auto-logged in the tracker with follow-up reminders ("Applied 5 days ago — sent a follow-up?") and outcome capture feeding the Advice Graph.
- **Apply Copilot (Phase 5 backlog, human-in-the-loop)**: for supported ATS forms (Greenhouse/Lever), an agent pre-fills the external application from the student's profile + tailored CV; the student reviews and taps Confirm. Mandatory confirmation — never submits on its own.
- **Fully autonomous auto-apply: explicitly deferred.** Portal ToS prohibit automated applications on user accounts (ban risk lands on the STUDENT), mis-submissions on a candidate's behalf create liability, and recruiter-side spam detection tanks response rates. Revisit when portals expose official apply APIs; the architecture (Copilot + tracker) is built to extend into it the day that's safe.

### 6.23 UI/UX, Motion Design & Landing Experience (NEW — quality bar)
**Benchmark: the Taurus AI site's polish. Aesthetic: premium, calm, cinematic. Motion serves comprehension and delight — never noise.** (Matches the brand's ad-creative principle: premium calm delivery.)

**Motion system (token-based, used everywhere):**
- Stack: Framer Motion (page transitions, shared layouts, micro-interactions) + scroll-linked reveals; optional Lenis smooth scroll on public pages only.
- Motion tokens: durations fast 150ms / base 250ms / slow 400ms / cinematic 700ms; one signature easing curve (custom cubic-bezier) used platform-wide; stagger 60–80ms for lists/grids.
- Signature moves: scroll-reveal (fade + 20px rise, triggers once), animated stat counters in IBM Plex Mono, PRI/progress rings that draw in on load, card hover lift + soft shadow, skeleton shimmer loaders, subtle page fade-slide between portal routes, confetti bursts reserved for real wins (offer celebrations, badges, course completion — scarcity keeps them special).
- **Hard rules**: animate transform/opacity only (60fps), respect `prefers-reduced-motion`, no scroll-jacking, motion never delays content interactivity, and the landing page keeps **LCP < 2.5s / CLS < 0.1** — Meta ads traffic converts on speed; a beautiful slow page is a broken funnel.

**Navigation (easiest-possible):**
- Student portal: persistent left sidebar (Dashboard, Classes, Recordings, Practice, Jobs, Mentors, Fees, Support), mobile bottom tab bar with the same five core destinations, breadcrumbs on depth-2+ pages.
- **Max two taps to anything**; the Coach Panel's Next Best Action is always the visually loudest element on the dashboard.
- Command palette (Cmd/Ctrl+K) across all portals — jump to any lesson, ticket, student (staff), or action.
- Empty states always teach ("No recordings yet — your first class is Thursday 7 PM"), never dead-end.

**Landing page — full-feature transparency (decision-making made easy):**
Every capability showcased so the candidate knows exactly what they're buying — nothing hidden:
1. **Hero**: sharp promise + Free Masterclass CTA, ambient premium motion (slow gradient/parallax, nothing gimmicky).
2. **Proof Engine** ink-navy panel with live animated counters (placements, promise-kept stats) + internal-data disclaimer.
3. **"Your Journey" scroll timeline**: Masterclass → Free Bootcamp → Batch → AI Coach → Mocks → Placement — a progress line that draws as you scroll, mirroring the actual product pipeline.
4. **Feature showcase sections** with live micro-demos (animated UI mockups of the real product): AI Personal Coach panel, live classes + recordings library, voice mock interviewer with real-interview intelligence, ATS CV generator, mentor 1:1 booking, Jobs-for-You feed, batch leaderboard, support desk with SLA promise.
5. **Transparent pricing**: per-course fees with a single/EMI toggle showing the live instalment schedule, and an honest **included-vs-paid table** (what's free forever vs what costs extra, with prices) — freemium transparency builds the trust that closes enrolments.
6. **Promise cards**: verify-green "What we promise in writing" vs warn-red "What we will never tell you."
7. **Success wall**: consented testimonials + offer celebrations from the review engine, auto-refreshed.
8. **Per-course syllabus download** (lead magnet) + FAQ + footer promise line ("Every promise in writing · Every call recorded & AI-monitored").
- Section content is CMS-driven (admin-editable, tenant-themed) so whitelabel tenants inherit the same landing architecture with their brand.

**Portal beauty standards**: same motion tokens; data-dense staff screens stay calm (motion minimal, information hierarchy maximal); dark-mode-ready CSS variables from day one; WCAG AA contrast throughout.

---

## 7. AI + Human Collaboration Model

| AI does | Human does |
|---|---|
| Monitors every signal 24/7; scores leads and students | Counselor makes the call/intervention |
| Drafts grades, feedback, syllabi, reports | Trainer verifies and owns final output |
| Answers doubts with citations | Trainer handles escalations |
| Runs unlimited mocks; benchmarks vs real interviews | Placement officer runs final mock; mentors coach 1:1 |
| Drafts CVs and gap reports | Placement officer approves |
| Drafts nudges within approved templates | Admin approves templates (compliance-linted) |

**Rule: no high-stakes action (final grade, placement flag, hard block beyond policy, off-template message) without a human checkpoint or explicit configured policy.**

---

## 8. Data Model (core tables outline)

`tenants`, `users`, `roles/permissions`, `students` (risk, pri, engagement, lead_stage), `leads` (source, utm, dedupe_phone, score, assigned_to), `lead_sources`, `crm_tasks`, `call_logs` (recording, transcript, sentiment), `sequences/sequence_steps`, `contact_timeline_events`, `funnel_events` (UTM), `masterclasses`, `event_registrations`, `programs/courses/modules/topics/lessons`, `syllabus_docs` (version, pdf_url, approved_by), `batches` (type: masterclass/bootcamp/paid, number, capacity, linked_source), `batch_members` (status), `live_sessions`, `session_changes` (cancel/reschedule log), `recordings`, `attendance`, `activity_events`, `code_submissions`, `quizzes/attempts`, `assignments/grades`, `mock_interviews`, `real_interview_bank`, `interview_transcripts` (source, file, consent_confirmed, parse_status), `transcript_extractions` (review status), `job_postings` (jd, extracted_skills), `job_feed_sources`, `job_feed_items` (external_url, source, freshness, quality_score), `job_matches` (student, relevance, gap_summary), `syllabus_recommendations` (evidence, approval status), `hiring_partner_feedback`, `salary_benchmarks`, `alumni_checkins`, `interview_debriefs`, `gap_reports`, `cvs`, `fee_plans`, `instalments`, `payments`, `payment_link_campaigns`, `vouchers` (source, value, expiry, redeemed), `reviews` (rating, text, video_url, consent, status), `access_blocks`, `mentors`, `mentor_availability`, `bookings`, `session_feedback`, `messages`, `notifications`, `tickets` (category, priority, status, assignee, sla_due), `ticket_messages`, `ticket_teams` (category→role/user map), `canned_responses`, `csat_ratings`, `escalations`, `entitlements`, `credit_wallets/credit_transactions`, `product_purchases` (packs, self-paced, Career+), `quota_usages`, `points_events`, `leaderboard_snapshots`, `badges`, `celebrations` (consent flag), `market_pulse_items`, `content_items` (podcast/video/post), `nps_responses`, `rage_signals`, `reports`, `jobs_board`, `applications`, `audit_logs`, `ai_events` (LLM cost log), `webhooks_log`.

---

## 9. Build Phases for Claude Code

**Phase 1 — Unified Funnel + Core LMS (weeks 1–3):** tenancy scaffold, public landing/courses/syllabus-download lead magnet, masterclass registration + reminders (12h/2h/5min), OTP auth + magic-link framework, batch management (auto/manual numbering, silo/bulk/CSV roster, transfers), course/lesson CRUD, Zoom sessions + gated join + webhook attendance + **cancel/reschedule with auto-notify**, Recordings tab, dashboard shell, lead capture foundation (leads table, sources, dedupe) so masterclass/syllabus signups land in CRM from day one.

**Phase 2 — Conversion Engine + Money + CRM (weeks 3–5):** **built-in CRM** (lead inbox with all capture sources incl. Meta Lead Ads webhook, dedupe, pipeline kanban, counselor tasks + assignment rules, contact timeline, voice-agent call logging, lead scoring), bootcamp batch type + auto-assignment to paid batch, personalized payment links (single + bulk), single/EMI (1-2-3) checkout with schedule preview, AutoPay + webhooks, fee widget + countdown + ladder + soft/hard block + recordings lock + instant unblock, **review-for-voucher engine + voucher manager admin page (₹/% toggle)**, WhatsApp Cloud API + email journeys, abandoned nudges, dunning + funnel dashboards, **Student Support Desk** (categories, team routing map, staff profiles/memberships, instant notifications, SLA timers + escalation, ticket workspace, CSAT), **Entitlement/credit engine + monetization settings page + self-paced recorded-course tier**.

**Phase 3 — AI Coach Layer (weeks 5–8):** telemetry pipeline + Judge0 code telemetry, Coach Panel + Next Best Action + PRI, AI tutor RAG, risk/lead scoring + counselor dashboard, module-MCQ auto-dispatch, AI grading with approval, AI syllabus generator, **support-desk AI layer (deflection answers, triage, sentiment queue-jumping, AI reply drafts, weekly themes report)**, recording→transcript→notes/quiz pipeline, weekly reports, trainer briefs, **batch leaderboards + points engine, motivation engine (consented offer celebrations + personalized gap guidance), Market Pulse + Content Hub feeds**.

**Phase 4 — Mocks, Mentors & Placement (weeks 8–10):** voice AI mock interviewer with quota engine + top-up packs, topic-mock prompts, real interview bank + gap reports, mentor calendar + booking + notifications, CV generator (auto-gen on completion + credit packs + ATS suite), placement module, end-of-course report, **review-protection suite (NPS pulses + detractor rescue, rage signals, pause/defer, LinkedIn optimizer, GitHub portfolio builder)**, Career+ subscription, **transcript ingestion pipeline (Parakeet/manual/audio) + Curriculum Intelligence engine (market-driven syllabus recommendations with trainer approval) + Live Job Feed with Apply Assist**, whitelabel theming UI, DPDP workflows.

Definition of done per feature: migrations + API + UI + queue jobs + tests (Pest/Playwright) + audit logging + seed data.

---

## 10. Security & Ops Checklist (pre-launch)

- [ ] Rotate ALL API keys (Razorpay, Zoom, Anthropic, WhatsApp) — never reuse anything that appeared in chats
- [ ] Webhook signature verification (Razorpay, Zoom, WhatsApp)
- [ ] Magic links: single-use, short expiry, action-scoped
- [ ] Rate limiting on auth + AI endpoints; per-student daily AI token budget
- [ ] Cross-tenant access tests must fail
- [ ] PII encryption at rest; TLS everywhere
- [ ] Telemetry consent at signup (DPDP)
- [ ] Voucher tied to platform testimonials, not Google reviews (policy risk)
- [ ] Queue workers supervised + failure alerting
- [ ] Staging with Razorpay test mode + Zoom sandbox
- [ ] Load target: 500 concurrent students, 20 concurrent live classes, 1,000 concurrent masterclass registrants

---

## 14. Platform Spec v1.0 Addendum (2026-07-15 — BINDING)

Source: `docs/BrowseJobs_Platform_Spec_v1.pdf` (Master Build Specification v1.0, July 2026; extracted text in `docs/browsejobs-platform-spec-v1.txt`). Where this addendum conflicts with earlier sections, **the addendum wins**. Stack recommendation in spec §5 is superseded by ADR 0005 (Laravel 11 + MySQL retained).

### 14.1 Identity & positioning
- BrowseJobs.ai — AI-driven IT skilling & placement platform. Founded London 2013, India 2020; office Whitefield, Bengaluru. Entity: IBrowseJobs Technologies Pvt Ltd. Domain browsejobs.ai (browsejobs.in 301s to it).
- Core differentiator: **the syllabus is reverse-engineered** — AI monitors up to ~50 real & mock interviews/day and the syllabus is rebuilt monthly around live demand.
- Positioning line: "This syllabus was not written. It was reverse-engineered." Primary tagline: "Built from real interviews."
- Contact: +91 86185 19825 · hello@browsejobs.ai · Mon–Sat 9:00–19:00 IST.

### 14.2 Design system deltas (see CLAUDE.md for the full binding set)
- New tokens: `--ink-2 #1B2A44`, `--green-bg #E6F7EF`.
- Semantic rules: green = free/verified only (never paid CTAs); red = refused-promise/error only; amber = stars/coach notes only; ONE blue primary CTA per view.
- Radii 14/22/999/10; soft shadow only; reveal = fade + 18px rise; every user-facing number in IBM Plex Mono.
- Signature components to exist in code: Kicker, ProofPanel (LISTEN → EXTRACT → REBUILD), StatBand, PromiseCards, FreeLadder, Disclaimer, script block, coach note.

### 14.3 Compliance (enforce in code)
- Never-claims list per spec §3.3 (no job guarantees, no fabricated experience, no salary promises, no certain-hiring claims).
- Mandatory disclaimer after every stat, stored once: "Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance."
- Footer line everywhere: "Every promise in writing · Every call recorded & AI-monitored."
- DPDP: consent checkbox + logged consent on every lead/enrol form; privacy/terms/refund pages with [CIN]/[GST]/Grievance-Officer placeholders; placement-fee agreement is signed (not click-wrap).

### 14.4 Fee model (replaces earlier illustrative pricing)
- Registration ₹30,000, payable only AFTER free masterclass + bootcamp; EMI 3×₹10,000.
- Placement fee = first 3 months' CTC, due only after offer acceptance, 6 monthly EMIs, ₹30,000 registration adjusted inside. Worked example @ ₹12 LPA: 3,00,000 − 30,000 = ₹2,70,000 over 6 EMIs.
- 30-day money-back guarantee, any reason, in writing. All money server-owned (never trust client-sent prices).

### 14.5 Programs (replaces placeholder course list)
- Live (4): Data Engineering · DevOps & Cloud · Python Backend · Data Analytics.
- Waitlist (3): Agentic AI · Cyber Security · ServiceNow.
- Each live course: ~8–9 modules, tools list, 3–5 CV-ready projects, outcomes, target roles (content in brochures — seed when supplied). Courses carry `status` (live/coming_soon).

### 14.6 Funnel spine (three free steps)
1. Free counselling + written Career Analysis Report (lead_type=counselling)
2. Free live Masterclass (lead_type=masterclass) — primary conversion & primary ad destination
3. Free 7-hour Python Bootcamp (lead_type=bootcamp)
4. → Paid registration ₹30,000 → enrolment → LMS access.
- Marketing mechanics: sticky mobile CTA ("Book Free Masterclass"), lead modal (masterclass/counselling/waitlist variants; name, WhatsApp phone, email, course), UTM capture persisted to lead, CRM POST + WhatsApp confirmation, analytics events (LeadFormOpen, Lead, InitiateCheckout, Purchase).

### 14.7 Open items requiring founder input (do NOT invent)
- ~~The exact three "Verify us on Naukri" checks~~ — RESOLVED from the 2026 brochures (Count the demand / Read 5 JDs / Pressure-test everyone).
- ~~Precise label/meaning of the ~90% stat~~ — RESOLVED: "of interview questions come from our material".
- ~~Per-course syllabus/tools/projects~~ — RESOLVED for DE / DevOps & Cloud / Data Analytics from the 2026 brochures (DE core excludes Kafka/streaming per founder; carried as optional self-study). **Python Backend brochure still needed.**
- Testimonials: 4 real ones seeded from the brochures; the bulk Google/WhatsApp reviews (1,000+ five-star per founder) need a REAL export — import with `php artisan reviews:import <csv>` (never fabricate reviews).
- Legal placeholders: [CIN], [GST], Grievance Officer name.
- Google sign-in: set GOOGLE_CLIENT_ID/SECRET to activate the button (hidden until configured).
