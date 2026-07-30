# BrowseJobs Employer Module — Requirements Document (PRD-E v1.0)

**Status:** Draft for founder review · **Date:** 2026-07-30
**Extends:** `browsejobs-lms-requirements.md` (PRD v1.4) — that document remains the single source of truth for shared systems (tenancy, design system, AI gateway, compliance). This document specifies the employer-facing product only.
**Related ADRs:** 0016 (multi-LLM gateway), 0029 (AI mock interviewer), 0031 (voice mocks & quotas), 0033 (CV generator), 0034 (placement pipeline), 0037 (candidates directory), 0047 (DPDP data requests).

---

## 0. Context

BrowseJobs is adding a two-sided marketplace: candidates complete **JD-specific, proctored voice mocks before applying**, so every application an employer sees is pre-interviewed, graded, and video-verified. Employers get a year-one free tier (credit-capped) with sourcing, AI-run L1/L2 interviews, and online tests. The employer module is the demand-side surface for this model.

**Positioning line (binding, honest-framing rules apply):** "Every application arrives pre-interviewed."

**Business rules that shape this module (from founder decisions, 2026-07):**
- Applying to any JD is **free** for candidates. Paid mock packs buy *preparation + advantage* (graded badge, ranked placement), never *access*. No surface may present payment as required to apply.
- Grading is **firewalled from candidate monetisation**. No purchase influences any score. This firewall is stated in the employer UI (trust page) and enforced in code (grading services have no dependency on billing state).
- Free employer tier is **credit-capped** per employer per month (sourcing credits + interview credits; defaults in §8.4, configurable per tenant).

---

## 1. Vision & North-Star Metrics

A recruiter should go from *posting a JD* to *a ranked, evidence-backed shortlist* with near-zero effort, and from shortlist to offer in **1–2 days**. The dashboard must feel like a premium command centre — calm, fast, numerate — per the PRD §6.23 quality bar ("Taurus-site polish, premium and calm"). Futuristic here means *instant, legible, alive with real data* — not decorative.

| North-star metric | Target |
|---|---|
| Time from JD published → first graded applicant visible | < 24 h |
| Time from shortlist → offer released (automated pipeline) | ≤ 2 days |
| Employer weekly active rate (of onboarded employers) | ≥ 60% |
| Recruiter actions required per hire (clicks on decisions, not admin) | ≤ 15 |

---

## 2. Personas & Roles (RBAC)

| Role | Description | Key permissions |
|---|---|---|
| **Employer Owner** | Signs up the company, owns billing/credits | Everything; manage members, API keys, integrations |
| **Recruiter** | Day-to-day sourcing and pipeline | JDs, pipelines, search, interviews, offers (release requires Owner or granted permission) |
| **Hiring Manager** | Reviews shortlists, watches replays, decides | Read pipelines + candidate evidence; approve/reject; comment. No JD/credit admin |
| **Interviewer (guest)** | Human-round participant | Scoped access: assigned candidates only, time-boxed |
| **Employer Admin (BrowseJobs internal)** | Support/ops | Impersonation with audit log, credit adjustments (audited) |

All roles are per-employer-workspace. A user may belong to multiple employer workspaces (agency case). Every permission check is policy-based (Laravel policies), never inline conditionals.

---

## 3. Information Architecture & Navigation

**Rule (binding, PRD §6.23): maximum two taps/clicks to anything.**

- **Left sidebar (desktop) / bottom tabs (mobile):** Dashboard · Jobs · Pipeline · Search · Analytics · Settings. Nothing else at top level.
- **⌘K / Ctrl-K command palette** (required at launch, not phase 2): jump to any JD, candidate, pipeline stage, setting; run actions ("release offer for <candidate>", "pause automation on <JD>"). Fuzzy search over entities + actions.
- **Global candidate omnisearch** in the top bar — always present, from every screen.
- **Persistent context rail:** inside a JD, a right-hand rail shows live pipeline counts; clicking a count deep-links to that stage filtered.
- **Teaching empty states everywhere** (PRD §6.23): a new employer's dashboard is a guided "post your first JD" journey, never a blank grid. No dead ends: every empty state names the next action and links it.
- **Keyboard-first review flow:** in shortlist review, `J/K` next/previous candidate, `A` advance, `R` reject (with confirm), `V` open video replay. Reviewing 50 candidates must be possible without touching the mouse.

---

## 4. Functional Requirements

Each feature below follows the CLAUDE.md Definition of Done: migrations + Actions + Form Requests + Resources + UI with loading/empty/error states + queued jobs + Pest (happy, auth, cross-tenant denial) + Playwright flow + audit logging + seeds.

### F1 — Employer Onboarding & Workspace
- Self-serve signup with company email verification; company profile (name, logo, GSTIN optional, industry, size, locations).
- Invite team members by email with role selection; pending-invite management.
- Workspace switcher for multi-workspace users.
- Onboarding checklist widget on dashboard (post JD → review first applicants → set an automation) with progress; dismissible, never nagging.
- **Events:** `EmployerRegistered`, `EmployerMemberInvited`, `EmployerMemberJoined`.

### F2 — JD Management
- Create/edit JD: title, role family, skills (tagged, drives matching), experience band, locations/remote, CTC band (visible/hidden toggle), openings count, screening knockout questions.
- **AI assist (via `AiGateway`):** draft JD from a title + bullet notes; extract structured skills from a pasted JD; flag vague requirements. All AI-drafted content is editable before publish — employer publishes, not the AI.
- On publish, the platform **auto-generates the JD-specific mock** (questions + grading rubric) via the existing mock engine (ADR 0029/0031). Employer can preview the mock, regenerate sections, or pin their own questions. Rubric is visible to the employer (transparency) but **not** editable per-candidate (comparability).
- JD states: Draft → Published → Paused → Closed (auto-close on openings filled, confirm first). Cloning a JD clones its mock config.
- **Events:** `JobPublished`, `JobPaused`, `JobClosed`, `JdMockGenerated`.

### F3 — Applications & the Graded Pool
- All applicants visible, **graded applicants ranked on top** with score, badge, and match rationale; ungraded applicants listed below with a one-click "invite to mock" nudge (sends the candidate a free-to-view invitation; the candidate's payment relationship is never surfaced to the employer).
- Match rationale is a short generated explanation per candidate ("Why #1 is #1") citing skills, mock evidence, and readiness signals — cached, regenerated on profile change.
- Bulk actions: advance, reject-with-reason (templated, candidate-respectful), export.
- **Events:** `ApplicationReceived`, `ApplicationAdvanced`, `ApplicationRejected`.

### F4 — Candidate Profile & Evidence View
The core trust surface. One screen, three panes:
- **Identity & Trust:** photo, Trust Score (§7), verification checklist (ID, education, employment history, links), proctoring summary (flags with snapshot evidence, warning counts, face-match status across rounds).
- **Evidence:** mock/L1/L2 video replays with seekable transcript (click transcript line → video seeks), per-rubric-dimension scores with drawn-in progress rings, downloadable graded report PDF.
- **Journey:** BrowseJobs readiness signal — mocks taken over time, score trajectory sparkline, courses/bootcamps completed. Historical figures presented with the standard `<Disclaimer/>` where aggregate stats appear.
- Hiring-manager comments thread per candidate (internal to workspace); @mentions notify.
- **Privacy:** employer sees only what the candidate consented to share on application (DPDP, ADR 0047). Consent scope is versioned; revocation propagates (§10).

### F5 — Smart ATS Pipeline
- Kanban + table views of stages: `Applied → Graded → Shortlisted → L1 → L2 → Custom rounds… → Human round → Offer → Hired/Closed`. Custom stages insertable (coding round, assignment).
- Drag between stages (manual) — every transition, manual or automated, is an event with actor attribution shown in the candidate timeline.
- **L1/L2 AI interviews:** employer configures per-JD question source — own bank, AI-generated (previewable), or blended. Candidate is notified (WhatsApp/email), completes async within a configurable window; same-day L1→L2 supported. Proctoring identical to mocks.
- **Human round scheduling:** slot picker synced to interviewer availability; candidate self-serve booking via the existing scheduling module (ADR 0032 patterns); reminders automated.
- **Offer release:** offer details form → generated offer letter (employer template or platform default) → release through platform → candidate accept/decline with digital acknowledgment. Offer release always requires an explicit human action by a permitted role — **never automated**.
- **Events:** `StageAdvanced`, `InterviewScheduled`, `InterviewCompleted`, `InterviewGraded`, `OfferReleased`, `OfferAccepted`, `OfferDeclined`.

### F6 — Automation Rules Engine
Per-JD rules, every rule optional, every automated action reversible and attributed to its rule in the timeline:
- **Triggers:** score thresholds ("mock ≥ 70% → auto-shortlist"), stage outcomes ("L1 ≥ 75% → unlock L2 immediately"), time SLAs ("no L1 attempt in 48 h → reminder cascade", "no employer review in 24 h → digest nudge").
- **Actions:** advance stage, unlock next interview, send templated nudge, park with reason, notify a role.
- Rules are validated at save (no cycles, thresholds within rubric bounds) and simulated: "against last 30 days of applicants, this rule would have advanced 41 and parked 12" before enabling.
- Hard limits: automation can never *reject terminally* (only park for review) and never release offers.
- Implementation: rules stored as data (JSON conditions), evaluated in queued listeners on domain events — never inline in requests. Idempotent, retry-safe per CLAUDE.md.
- **Events:** `AutomationRuleTriggered` (carries rule id, candidate, action) — feeds the audit log.

### F7 — Candidate Search & Talent Pool
- **Structured filters:** skills, experience band, location/remote, notice period, score ranges, trust-score tier, readiness signals, "graded in last N days".
- **Semantic search:** free-text ("Django dev comfortable with on-call, Bangalore, joins in 30 days") → embedding search over consented profiles + structured filter extraction, with the parsed interpretation shown as removable chips (the employer always sees *why* results matched).
- **Saved searches + alerts:** "notify me when a new candidate matches" (in-app + digest email).
- **Talent pool memory:** every candidate ever graded for this employer remains searchable (consent-scoped); "similar to this candidate" pivot.
- Performance: filter round-trip < 300 ms at 1M profiles (vector index + MySQL composite indexes; see §11).

### F8 — Credits & Plan Surface
- Dashboard widget: sourcing credits and interview credits remaining this month (mono numerals), burn-down sparkline, "what happens at 0" clearly explained (graceful degradation: pipeline keeps working, new sourcing pauses — never data lockout).
- Defaults (configurable per tenant): 100 sourcing credits + 350 interview credits/month on the free year. Over-cap requests route to a human (sales) — no self-serve overage billing in v1.
- All credit mutations audited.

### F9 — Notifications & Digests
- Channels: in-app inbox, email, WhatsApp (via Messaging Hub, ADR 0009). Per-user preferences with sane defaults; hiring managers get less, recruiters more.
- Daily digest: new graded applicants per JD, pipeline movements, SLA breaches, credit status. One email, scannable, mono numbers.
- Real-time (in-app): new graded applicant on a watched JD, automation actions, offer responses.

### F10 — Analytics
- Per-JD and workspace-level: funnel conversion (applied → graded → shortlisted → interviewed → offered → hired), time-in-stage, time-to-hire trend, score distributions per rubric dimension, source quality, automation efficacy (actions taken vs. overridden).
- Every chart exportable (PNG/CSV). Weekly PDF digest an owner can forward internally ("the report their HR head shows their CEO").
- Chart/visual spec in §5.

### F11 — External Data Integration ("connect their own data")
The employer can connect BrowseJobs to their own systems. All integration surfaces are **workspace-scoped, key-scoped, and audited**.

- **REST API v1 (`/api/v1/employer/…`):** read JDs, applications, candidates (consent-scoped fields only), pipeline events; write JDs, stage transitions, notes. Cursor pagination, consistent error envelope, versioned.
- **API keys:** created per workspace by Owner; **scoped** (read-only / read-write; resource scopes selectable); prefix-identified (`bj_live_…`); shown once; rotation with overlap window; last-used timestamp; per-key rate limits. Keys hashed at rest.
- **Webhooks:** employer registers HTTPS endpoints for event types (`application.graded`, `stage.advanced`, `offer.accepted`, …). HMAC-signed payloads (per CLAUDE.md webhook rules — we sign ours exactly as we verify theirs), thin payloads (IDs + type; fetch for detail), retries with backoff, auto-disable on sustained failure with owner notification, delivery log UI with replay.
- **Inbound import:** CSV/ATS import of their existing candidate pool into a private workspace pool (kept strictly separate from the BrowseJobs graded pool; private-pool candidates can be *invited* to mocks). Field mapping UI, dedupe by email/phone hash, DPDP notice obligations surfaced at import time (the employer attests to consent for imported data).
- **Exports:** everything the employer owns is exportable (CSV/JSON) — pipelines, notes, analytics. Candidate evidence (video) exports are watermarked, expiring signed URLs, and audited.
- **SSO (v1.1):** SAML/OIDC for enterprise workspaces; SCIM deferred.
- **Anti-scraping:** bulk profile reads rate-limited and anomaly-monitored; candidate contact details are progressively disclosed (revealed only at Shortlisted stage or later) to prevent pool harvesting.

### F12 — Trust Centre Page
A public-facing (per-tenant) page inside the employer portal explaining: how grading works, the monetisation firewall, proctoring methodology, verification levels, data handling & DPDP posture. This is a sales asset as much as documentation. All claims honest-framing compliant; the mandatory `<Disclaimer/>` follows any statistic.

---

## 5. Dashboard, Data-Viz & UI Specification

### 5.1 Design system (binding — no exceptions)
Everything consumes the CLAUDE.md / Platform Spec §2 tokens via CSS variables (whitelabel-ready): Ink `#0A1220`, Trust blue `#1B6DF0` (the single primary action colour — one primary CTA per view), Sky `#E7F1FE`, Verify green `#0BA860` (verified/success only), Warn red `#D64545` (danger only), Amber `#F5A623` (review stars/coach notes only), Paper `#F6F9FE`, Line `#DCE6F5`, Muted `#5A6B85`. Fonts: **Sora 800/700** display (-0.02em), **Inter** body, **IBM Plex Mono 500/600 for every number a user reads** — scores, counts, credits, durations, percentages, dates — plus kickers and labels. Radii 14/22/999/10; soft shadows only; dark-mode-ready variables from day one; WCAG AA.

"Futuristic" is achieved through **motion, density control, and live data** — not new colours or gradient decoration. The look stays premium and calm.

### 5.2 Dashboard layout ("the command centre")
- **Top strip — 4-up mono stat band** (signature pattern): Active JDs · New graded applicants (7d) · Median time-to-shortlist · Offers this month. Animated mono counters on load (count-up, 700 ms, signature easing); each stat clickable → filtered view. Stat band is followed by the standard disclaimer in muted mono when figures are historical aggregates.
- **Next Best Action card — the loudest element** (mirrors the student-side rule): the one thing that most needs the recruiter now ("12 graded applicants waiting on *Backend Engineer* — review now", "L2 done for 3 candidates — release or schedule human round"). Single blue CTA.
- **Pipeline pulse:** horizontal funnel bar per active JD — stage segments proportional, drawn-in on load, live-updating via polling/websocket; hover reveals mono counts; click deep-links.
- **Activity feed (right rail):** real-time stream — graded applicants arriving, automation actions, offer responses. Each row: line icon (1.5–2 px stroke, blue/ink), one line of text, mono timestamp.
- **Credits widget** (§F8) and **onboarding checklist** (new workspaces) complete the grid.
- Grid is responsive: 12-col desktop → stacked mobile; the stat band and Next Best Action always first.

### 5.3 Chart specification
Library: **Apache ECharts** (canvas, themable via CSS-variable-fed theme object, performant at our data sizes) wrapped in a single `packages/shared`-typed `<Chart>` component family. One theme file maps design tokens → chart palette; **no hex values in chart configs**.

| Chart | Type | Spec |
|---|---|---|
| Hiring funnel | Horizontal funnel/bar | Stage-proportional, blue scale (Deep navy → Trust blue → Sky); conversion % in mono between stages; draw-in once on first view |
| Time-to-hire trend | Line + area | 1.5 px line, 8% opacity area fill, faint gridlines (Line token), emphasized endpoint dot with mono label; period comparison as muted dashed line |
| Score distribution | Histogram | Per rubric dimension; employer's threshold rendered as a labelled reference line; bars in Sky with Trust-blue hover |
| Rubric profile (per candidate) | Progress rings | Draw-in rings (PRD §6.23 standard component), mono % centred; green only when a dimension is "verified strong", otherwise blue |
| Score trajectory (candidate) | Sparkline | Inline, 40×160 px, endpoint emphasized; no axes, tooltip on hover |
| Automation efficacy | Stacked bar | Actions taken vs overridden; overridden segment in Muted — **never red** (an override is not an error) |
| Stage-time heatmap | Calendar heatmap | Sky→Deep-navy scale; identifies where candidates stall |

Motion rules (binding): animate `transform`/`opacity` only; durations from `motion.ts` tokens (150/250/400/700 ms, one signature easing, 60–80 ms stagger); charts draw in **once**; `prefers-reduced-motion` disables all of it (charts render final state instantly); motion never blocks interaction. Numbers in tables use `font-variant-numeric: tabular-nums`.

### 5.4 Component standards
Modern component-library quality bar (shadcn/radix-style primitives already in use in `apps/web`): accessible popovers/dialogs/comboboxes with full keyboard support and focus management; skeleton shimmer on every loading state; card hover lift; optimistic UI on stage transitions with rollback toasts; virtualized lists for candidate tables (10k+ rows smooth); route transitions use the portal fade-slide standard. Video replay player: custom-skinned, transcript-synced, snapshot-flag markers on the timeline, playback speed control, watermarked with viewer identity.

### 5.5 Performance budget
Dashboard LCP < 2.0 s on mid-tier hardware; interaction latency < 100 ms perceived (optimistic updates); chart render < 300 ms after data; search-as-you-type < 300 ms round trip. Lighthouse CI on the employer shell.

---

## 6. Candidate Search UX (detail)

- Single search surface: one input, natural language or structured; parsed filters render as removable chips so the query is always inspectable.
- Results as **evidence cards**: name (or anonymized handle pre-consent-stage), top-3 matched skills highlighted, mock score + trust tier, readiness sparkline, one-line match rationale. Grid/table toggle.
- Facet rail with live counts; zero-result states suggest relaxations ("remove 'immediate joiner' → 34 candidates").
- Every search auto-saveable; saved search → alert in two clicks (within the two-tap rule).

---

## 7. Trust Score & Verification (employer-visible spec)

Composite score with transparent components (never a black box — each component and its status is listed):
1. **Identity:** DigiLocker ID verification; PAN check; face-match consistency across mock → L1 → L2 (liveness).
2. **Education:** DigiLocker certificate pulls where available; manual upload + AI cross-check otherwise (marked as "document-verified" vs "self-declared").
3. **Employment history:** UAN/EPFO service-history consent flow where the candidate opts in; else self-declared with LinkedIn cross-reference.
4. **Links:** GitHub/portfolio/LinkedIn presence and activity signals.
5. **Integrity:** proctoring record — warning counts, window-switch events, snapshot flags (evidence viewable), AI-answer-likelihood signal on text rounds.

Tiers: Verified+ / Verified / Basic / Flagged. **Flagged never auto-rejects** — it surfaces evidence for human review. All verification API integrations (DigiLocker, EPFO, PAN) are queued jobs with graceful degradation and provider-failure states; each needs its own ADR before build (external dependency + cost per check).

---

## 8. Security Requirements ("extreme security" — enforceable list)

1. **Tenancy:** every employer-module model uses `BelongsToTenant` + employer-workspace scope; every feature ships the cross-tenant denial test (CLAUDE.md non-negotiable) **plus** a cross-*workspace* denial test (two employers, same tenant).
2. **AuthN:** email verification mandatory; TOTP 2FA available to all, enforceable workspace-wide by Owner; session device list with remote revoke; new-device notification.
3. **AuthZ:** policy classes per resource; guest interviewer access is time-boxed, candidate-scoped tokens; impersonation (internal) always audited and banner-visible.
4. **Candidate media:** videos/snapshots in private object storage; access via short-lived signed URLs bound to viewer identity; player watermarks viewer email + timestamp; all replay views audit-logged (who watched what, when).
5. **API surface:** keys hashed at rest, scoped, rate-limited per key and per workspace; webhook payloads HMAC-signed; inbound integrations verified before processing (existing CLAUDE.md webhook rule).
6. **Progressive disclosure:** candidate PII (phone/email) hidden until Shortlisted; bulk-read anomaly detection with automatic throttle + alert (anti-harvesting).
7. **Audit log:** every stage transition, automation action, offer event, credit mutation, permission change, evidence view, export, key/webhook change. Employer-visible audit trail per candidate (their actions); full trail internal.
8. **DPDP:** consent scope versioned per application; revocation cascades (profile hidden from search, evidence access revoked, employer notified); data-request flows extend ADR 0047 to employer-held views; imported private-pool data carries employer attestation.
9. **Grading firewall (structural):** grading/report services must have no read path to billing/purchase state; enforced by module boundaries and an architectural test (grading namespace may not depend on billing namespace).
10. **Rate limiting** on auth, search, AI endpoints (existing rule); per-workspace AI token budgets in the service layer.
11. **Secrets:** nothing in code/seeds/docs; `.env.example` placeholders only (existing rule).

---

## 9. Data Model (outline — migrations per CLAUDE.md rules)

`employers` (workspace) · `employer_members` (user, role) · `jobs` (JD, state, mock config ref) · `jd_mocks` (generated question set + rubric, versioned) · `applications` (candidate, jd, consent scope version, current stage) · `pipeline_stages` (per-jd, ordered, type) · `stage_transitions` (application, from, to, actor: user|rule, occurred_at) · `automation_rules` (jd, trigger JSON, action JSON, enabled) · `automation_runs` (rule, application, action, outcome) · `interviews` (application, round type, status, video ref, transcript ref, scores JSON) · `offers` (application, letter ref, state, released_by) · `trust_verifications` (candidate, component, status, evidence ref, provider) · `employer_credits` (workspace, period, type, granted, used) · `employer_api_keys` (hashed, scopes, last_used) · `employer_webhooks` (+ `webhook_deliveries`) · `private_pool_candidates` (imported, workspace-scoped) · `evidence_access_log`.

Foreign keys + indexes on every `tenant_id`, `employer_id`, `job_id`, `application_id`, `candidate_id`. Money in paise. All AI calls through `AiGateway` → `ai_events`.

## 10. Domain Events (queued listeners only — never inline)

`EmployerRegistered` · `JobPublished` · `JdMockGenerated` · `ApplicationReceived` · `ApplicationGraded` · `StageAdvanced` · `AutomationRuleTriggered` · `InterviewScheduled` · `InterviewCompleted` · `InterviewGraded` · `OfferReleased` · `OfferAccepted` · `OfferDeclined` · `CreditConsumed` · `EvidenceViewed` · `ConsentRevoked` · `ApiKeyCreated/Rotated` · `WebhookDeliveryFailed`.

## 11. Non-Functional Requirements

- Search: < 300 ms at 1M candidate profiles (vector index — pgvector-equivalent for MySQL stack per ADR to be written: likely dedicated vector store (Qdrant) alongside MySQL, consistent with the CV-pipeline design).
- Interview grading report available < 60 min after interview completion (queued, Horizon-monitored, SLA-alerted).
- Availability target 99.9% for the employer portal; graceful degradation when AI providers fail (queue + retry, status surfaced honestly in UI — "grading delayed", never silent).
- All timestamps IST-aware per ADR 0042 (timezone handling).

## 12. Build Phases

| Phase | Scope |
|---|---|
| **E1 — Foundation** | Workspace/RBAC, JD management + auto-mock generation, applications list with graded ranking, candidate evidence view, basic dashboard (stat band + Next Best Action + activity feed), credits |
| **E2 — Pipeline & automation** | Smart ATS stages, L1/L2 orchestration, automation rules engine + simulation, notifications/digests, offer release |
| **E3 — Search & analytics** | Semantic search + saved alerts, talent pool, full analytics suite + weekly PDF digest |
| **E4 — Integration & enterprise** | Public API v1, API keys, webhooks, CSV/ATS import, exports, Trust Centre; SSO (v1.1) |

Each phase ships demo-able with seed data (a seeded employer workspace with realistic JDs, graded applicants, videos-stubbed evidence).

## 13. Open Questions (founder input required — do not invent)

1. Verification providers & budget: DigiLocker/PAN/EPFO integrations have per-check costs — which components are launch-mandatory vs v1.1?
2. Anonymized-until-shortlist: hide candidate name/photo until Shortlisted (bias reduction + anti-poaching) — on by default, off by default, or employer choice?
3. Offer letters: platform-generated template acceptable for launch, or employer-uploaded templates required day one?
4. Free-tier credit defaults (100 sourcing / 350 interviews) — confirm numbers.
5. WhatsApp sender identity for employer-triggered candidate notifications (BrowseJobs number vs per-employer branding).
6. Coding-round provider (build minimal in-house vs integrate a third party) — needs an ADR either way.
