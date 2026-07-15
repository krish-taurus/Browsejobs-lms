# CLAUDE.md — BrowseJobs LMS Build Rules
Read `browsejobs-lms-requirements.md` (PRD v1.4) before any task. It is the single source of truth. If a task conflicts with the PRD, stop and ask.

## Project
Unified AI-driven LMS + Education CRM for IBrowseJobs Technologies. Multi-tenant, whitelabel-ready. Full lifecycle: Ad → Masterclass → Bootcamp → Paid Batch → Learning → Placement.

## Monorepo Structure
```
/apps/api        Laravel 11 (PHP 8.3) — REST API, queues, webhooks
/apps/web        Next.js 15 + TypeScript — public site + student/trainer/admin portals
/packages/shared TypeScript types generated from API resources (single source for DTOs)
/docs            PRD, ADRs (architecture decision records — write one per major decision)
/docker          docker-compose for local dev (MySQL 8, Redis, Mailpit, MinIO)
```

## Backend Conventions (Laravel)
- PHP 8.3, `declare(strict_types=1)` everywhere, PSR-12, Laravel Pint for formatting.
- Pattern: thin Controllers → Form Requests (validation) → Action classes (single-purpose, in `app/Actions/{Domain}/`) → Models. No business logic in controllers or models.
- API: versioned `/api/v1/`, JSON:API-ish resources via Laravel API Resources. Consistent error envelope: `{error: {code, message, details}}`.
- Events are first-class: `TopicCompleted`, `ModuleCompleted`, `PaymentCaptured`, `PaymentOverdue`, `SessionRescheduled`, `TicketCreated`, etc. Automations are queued Listeners — NEVER inline in the request cycle.
- Every AI call, message send, PDF render, Zoom API call = queued Job (Horizon). Jobs are idempotent and retry-safe.
- Migrations: never edit a merged migration; add a new one. Foreign keys + indexes on every `tenant_id`, `student_id`, `batch_id`.

## Frontend Conventions (Next.js)
- App Router, Server Components by default; Client Components only where interactivity requires.
- TypeScript strict, no `any`. Zod for all API payload validation at the boundary.
- Tailwind with design tokens only (below). No hardcoded hex values in components.
- State: React Query for server state; minimal client state.
- Public pages (`/`, `/courses`, `/courses/[slug]`, masterclass pages) must be statically renderable + SEO-complete (metadata, OG tags, JSON-LD Course schema).

## Motion & UX Standards (PRD §6.23 — quality bar: Taurus-site polish, premium and calm)
- Framer Motion everywhere; motion tokens in one `motion.ts`: durations 150/250/400/700ms, ONE signature easing curve, stagger 60–80ms. Never inline ad-hoc durations/easings.
- Animate `transform` and `opacity` only. Respect `prefers-reduced-motion` (wrap in the shared `<Motion>` primitives). No scroll-jacking. Motion must never block interactivity.
- Standard components: scroll-reveal (fade + 20px rise, once), animated mono counters, progress rings that draw in, skeleton shimmer, card hover lift, portal route fade-slide, confetti ONLY for offers/badges/completion.
- Landing page performance budget: LCP < 2.5s, CLS < 0.1 (ad traffic converts on speed). Lighthouse check in CI for public pages.
- Navigation: max two taps to anything; sidebar + mobile bottom tabs + Cmd/Ctrl+K command palette; teaching empty states, never dead ends; Next Best Action is always the loudest element on the student dashboard.
- Dark-mode-ready CSS variables from day one; WCAG AA contrast.

## Design System (BINDING — Platform Spec v1.0 §2; BrowseJobs = default tenant theme, via CSS variables)
Ink navy `#0A1220`, Ink-2 `#1B2A44` (secondary text), Deep navy `#0E3FA9`, Trust blue `#1B6DF0`, Sky `#E7F1FE`, Verify green `#0BA860`, Green-bg `#E6F7EF`, Warn red `#D64545`, Amber `#F5A623`, Paper `#F6F9FE`, Line `#DCE6F5`, Muted `#5A6B85`.
**Semantic colour rules (hard):** green = free/verified/kept-promise/success ONLY — never a paid CTA. Red = refused-promise/danger/error only — never decorative. Amber = review stars + coach notes ONLY. Blue is the single primary action colour — ONE primary CTA per view.
Fonts: Sora 800/700 display (letter-spacing -0.02em), Inter 400/500/600 body, IBM Plex Mono 500/600 for **every number a user reads** (prices, stats, counts, dates, durations, %) plus kickers/labels/code — this is a brand signature.
Component rules: radius cards 14px / large panels 22px / pills 999px / inputs 10px; shadows soft only (`0 10px 40px rgba(10,18,32,.10)`); buttons primary = solid blue + subtle glow, ghost = line border + ink text; icons = line icons 1.5–2px stroke in blue/ink; sections ~72px vertical desktop / ~52px mobile; scroll reveal = fade + 18px rise, once, `prefers-reduced-motion` disables ALL motion.
Signature patterns: mono uppercase kicker (blue; green when the section is about something free/verified), ink Proof Engine panel (LISTEN → EXTRACT → REBUILD + live stats), 4-up mono stat band (ALWAYS followed by the disclaimer), green/red promise cards, free ladder (3 green FREE rungs + 1 blue STEP 04 paid rung), blue left-rule script block, amber left-rule coach note. Footer line: `Every promise in writing · Every call recorded & AI-monitored.` All colors/fonts consumed via CSS variables so whitelabel theming works.

## Brand Voice & Compliance (BINDING — Platform Spec v1.0 §3; enforce in code)
- Voice: direct, honest, confident, warm. Short sentences. NO hype adjectives ("world-class", "revolutionary", "best-in-class"). Radical honesty is the sales strategy.
- Positioning: "This syllabus was not written. It was reverse-engineered." Primary tagline: "Built from real interviews."
- **NEVER render anywhere** (site, LMS, emails, meta): "guaranteed job" / "100% placement" / "assured placement"; any fabricated-experience claim; any fixed salary promise; any claim that hiring is certain. Honest framing instead: "Nobody can guarantee employment — the market decides. What we put in writing: [the process]."
- **Mandatory disclaimer** immediately after ANY stat (98%, ~90%, 50/day, salary figures, 5,000+, 4.9/5), in muted mono, stored ONCE as `DISCLAIMER` and rendered via the shared `<Disclaimer/>` component: `Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.`
- Fee model (render exactly; all figures server-owned, never client-sent): Registration ₹30,000 payable only AFTER the free masterclass + bootcamp; EMI 3×₹10,000. Placement fee = first 3 months' CTC, due only after the student accepts an offer, 6 monthly EMIs, with the ₹30,000 adjusted inside it. 30-day money-back guarantee, any reason, in writing.
- Funnel spine (three free steps → paid): free counselling + written Career Analysis Report → free live Masterclass (primary conversion) → free 7-hour Python Bootcamp → paid registration. Every marketing CTA funnels to "Book Free Masterclass".
- Contact (footer/contact surfaces): +91 86185 19825 · hello@browsejobs.ai · Whitefield, Bengaluru · Mon–Sat 9:00–19:00 IST.
- Stack note: Platform Spec §5 recommends Next+Postgres; the stack remains Laravel 11 + MySQL per ADR 0005 — do not "migrate" to the spec stack.

## Multi-Tenancy Rules (non-negotiable)
- Every domain model uses `BelongsToTenant` trait → global scope on `tenant_id`.
- Every feature's test suite MUST include a cross-tenant denial test (tenant A cannot read/write tenant B data). A feature without this test is not done.
- Tenant resolution: by domain for public pages, by authenticated user for portals.

## Security Guardrails (hard rules)
- NEVER write secrets, API keys, or tokens in code, seeds, tests, or docs. `.env.example` with placeholder names only. If a real key appears anywhere, stop and flag it.
- Verify webhook signatures (Razorpay, Zoom, WhatsApp) before processing; reject unsigned.
- Magic links: signed, single-use, ≤24h expiry (≤15min for payment/auth actions), scoped to one action, consumed atomically.
- Rate limit auth, OTP, and AI endpoints. Per-student daily AI token budget enforced in the AI service layer.
- All money math in paise (integers). Never floats for currency.
- Audit log entry for: grade changes, fee waivers, access blocks/unblocks, roster changes, role changes, ticket escalations.

## AI Service Layer
- Single `app/Services/AI/` gateway wrapping the Anthropic API. Every call logs to `ai_events` (purpose, model, tokens, cost, latency).
- Prompts live in `resources/prompts/` as versioned files — never inline strings.
- AI outputs that face students (grades, reports, syllabi) go through the human-approval flow defined in PRD §7 unless the PRD marks them auto-send (nudges within approved templates, coach panel, tutor answers).

## Definition of Done (every feature)
1. Migrations + models + Action classes
2. API endpoints + Form Requests + Resources
3. UI wired with loading/empty/error states
4. Queued jobs for side effects
5. Pest tests (happy path + auth + cross-tenant denial) and Playwright test for the user flow
6. Audit logging where PRD requires
7. Seed data so the feature is demo-able immediately
8. `php artisan test` and `npm run build` pass

## Never Do
- Never skip or delete failing tests to make a build pass.
- Never modify `.env`, delete migrations, or force-push.
- Never call external APIs in tests — fake/mock all (Zoom, Razorpay, WhatsApp, Anthropic).
- Never mark a PRD feature "done" if any Definition-of-Done item is missing — say what's missing instead.

## Commands
```
composer test          # Pest suite (apps/api)
composer lint          # Pint
npm run test:e2e       # Playwright (apps/web)
npm run typecheck
docker compose up -d   # local stack
```
