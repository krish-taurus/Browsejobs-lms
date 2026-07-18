# Go-Live Guide — Taking BrowseJobs LMS Live

Plain-English, start to finish. If you read one document, read this. It says **what to do, in order,
and who does it**. Where a step needs technical detail, it points to the deeper doc — but you can follow
the shape of the whole launch from here.

**Who's who in this guide:**
- 👤 **You (founder/owner)** — accounts, keys, business/legal values, the final sign-off.
- 🛠️ **Developer** — the server and the technical steps.

Rough time: a focused developer needs **1–2 days** for a first staging setup, then **half a day** for
the production cutover once staging is signed off.

---

## The app in one picture

Four moving parts, all on one VPS to start:

1. **Web app** (Next.js) — what visitors and students see: the public site + the student/trainer/admin
   portals.
2. **API** (Laravel) — the brain: data, logic, payments, AI, everything the web app talks to.
3. **Background worker + scheduler** — does slow/timed work off to the side: sending WhatsApp/email,
   generating PDFs, nightly scores, daily nudges. **If this isn't running, messages and automations
   silently stop** — it's the #1 thing people forget.
4. **Data stores** — MySQL (the database), Redis (fast queue/cache), and S3-style storage (recordings,
   CVs, certificates).

Payment/Zoom/WhatsApp providers call the API back through **webhooks** (secure notifications) when
something happens.

---

## Phase 0 — Get these ready first 👤

Gather these before the developer starts, so setup isn't blocked waiting on you:

**Accounts & keys** (the developer will plug them in — see `env-checklist.md` for exactly where):
- [ ] **Razorpay** account → Key ID, Key Secret, Webhook Secret (start in **test mode** for staging)
- [ ] **Zoom** Server-to-Server OAuth app → Account ID, Client ID, Client Secret, Webhook Secret
- [ ] **WhatsApp** Cloud API (Meta) → Phone Number ID, Access Token, App Secret, a Verify Token you pick
- [ ] **AI provider** key (Anthropic or OpenAI-compatible)
- [ ] **Email/SMTP** sending account (e.g. Amazon SES, Postmark) → host, username, password
- [ ] **Object storage** bucket (AWS S3 Mumbai, DigitalOcean Spaces, or self-host) → keys + bucket name
- [ ] *(optional)* Meta Lead Ads, Google sign-in/Calendar, Vapi voice — only if you'll use them

**Business & legal** 👤 (fill these into the site before launch):
- [ ] Company **CIN** and **GST** numbers, **Grievance Officer** name/email, data-**retention windows**,
      **refund processing window** — all set in one file (`apps/web/src/content/landing.ts`, the `legal`
      block). Get the pages **reviewed by a lawyer**.
- [ ] The **placement-fee agreement** wording (signed, not click-wrap).

**Domain & server:**
- [ ] The **domain** `browsejobs.ai` (and `browsejobs.in` if you own it) with access to its DNS.
- [ ] A **VPS** (Hostinger VPS / DigitalOcean / Hetzner) — start at **4 vCPU / 8 GB RAM / 80 GB SSD** for
      production, 2 vCPU / 4 GB for staging. (Shared hosting will **not** work.)

---

## Phase 1 — Set up the server 🛠️

Developer provisions the VPS (Ubuntu 22.04/24.04) and installs PHP 8.3, Node 20, MySQL 8, Redis, and
Nginx. Exact commands: **`deploy-quickstart.md` step 1**.

---

## Phase 2 — Install the app 🛠️

Clone the repo, install dependencies, create the database, and seed the starter data (roles, the
BrowseJobs tenant, the 7 courses, and a demo student). Commands: **`deploy-quickstart.md` steps 2–4**.

---

## Phase 3 — Configure it 🛠️ (with your keys from Phase 0)

The developer copies `.env.example` → `.env` in both apps and fills in every value using
**`env-checklist.md`** — which lists each key, what it's for, and which ones are secrets. This is where
your Phase-0 accounts and keys get plugged in.

---

## Phase 4 — Turn on the background worker + scheduler 🛠️

Install the two services (worker + web app) and the one scheduler cron line. This is what makes
messages send and automations run. Commands: **`deploy-quickstart.md` steps 5–6**. Detail on all 19
scheduled jobs: **`deployment.md` §4–5**.

> ⚠️ On **every** future update, the worker must be **restarted** or it keeps running old code. The
> redeploy commands in `deploy-quickstart.md` do this.

---

## Phase 5 — Put it on the internet with HTTPS 🛠️

Point Nginx at the API and web app and get free TLS certificates (so the site is `https://`). The API
lives at `api.browsejobs.ai`, the site at `browsejobs.ai`. Commands: **`deploy-quickstart.md` step 7**.

---

## Phase 6 — Connect the integrations 🛠️

In each provider's dashboard, set the **webhook URL** so it can notify the app (URLs are listed in
`env-checklist.md`):
- Razorpay → `https://api.browsejobs.ai/api/v1/webhooks/razorpay`
- Zoom → `…/webhooks/zoom` · WhatsApp → `…/webhooks/whatsapp` · Meta Lead Ads → `…/webhooks/meta-lead`

Then send a test payment/message and confirm it arrives.

---

## Phase 7 — Test the whole journey on staging 👤🛠️

Before going anywhere near the real domain, both of you walk the **entire student journey** on staging
(with test-mode Razorpay + Zoom sandbox), start to finish:

**ad link → book free masterclass → get the reminder → join the Zoom class → attendance recorded →
pay the registration (test card) → enrolled → learn a module → take the quiz → do a mock interview →
generate a CV → see matched jobs → apply → track the application.**

Log in as the seeded demo student (phone **+91 90000 00001**, via the OTP code) to see every portal
page already populated. Full checklist: **`cutover-runbook.md` §3**.

---

## Phase 8 — Security & readiness sign-off 🛠️

Work the checklist in **`launch-readiness-audit.md`** until every item is green. The big ones:
- [ ] **Rotate every API key** to fresh production values (don't reuse anything from setup chats).
- [ ] Webhooks verify signatures · rate limits on · TLS everywhere.
- [ ] **Backups** of the database set up and a restore **tested once**.
- [ ] Monitoring + alerting on (so you know if the site or the worker goes down).
- [ ] A quick **load test** to confirm it holds the target traffic (`cutover-runbook.md` §4 has the plan).

---

## Phase 9 — Go live (the switch) 🛠️

Replace the old PHP site by pointing the domain at the new server, without losing Google rankings.
Follow **`cutover-runbook.md`** end to end:
1. Lower the domain's DNS TTL to 5 minutes (so the switch — and any undo — is fast).
2. Complete the redirect map from a crawl of the old site (so old links don't break).
3. In a quiet window, point `browsejobs.ai` at the new server.
4. Check the site + a few old URLs load, submit the sitemap to Google.

**If anything is badly wrong, roll back** by pointing the domain back at the old server — it takes
minutes and the old site is kept running untouched for 72 hours.

---

## Phase 10 — After launch 🛠️👤

- Watch the error monitor and the background worker for the first few days.
- **Common ops tasks:**
  - Check nothing's stuck: `php artisan queue:failed` (should be empty).
  - Add a new institute (whitelabel): super-admin → **Tenants** page.
  - Handle a DPDP data/deletion request: super-admin → **Data requests** page.
  - Update legal/company details: edit the `legal` block in `landing.ts`, rebuild the web app.
- Keep the old site archived for reference; decommission after 72 hours of clean metrics.

---

## The short version

**You get** the keys, the domain, the server, and the legal values ready (Phase 0).
**The developer** installs and configures it (Phases 1–6), you **test it together** on staging
(Phase 7), lock down **security** (Phase 8), then **flip the domain** (Phase 9) — with a safety net to
undo it. Then you **watch and maintain** (Phase 10).

Every technical step has exact commands in `deploy-quickstart.md`, every setting in `env-checklist.md`,
and the deeper "why" in `deployment.md`. The launch gate is one thing: the full journey works on
staging, witnessed by both of you.
