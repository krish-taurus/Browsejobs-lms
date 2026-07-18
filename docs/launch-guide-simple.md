# BrowseJobs LMS — Step-by-Step Launch Guide (beginner-friendly)

A complete, plain-English walkthrough from the finished code to a live website. Tailored to this setup:
**Hostinger · Zoom (2 licenses) · Razorpay · OpenAI or Claude · WhatsApp added later** (so **email is the
active notification channel** until WhatsApp is connected).

> **About secrets:** API keys and passwords are like house keys — never paste them into a chat or email.
> They go straight onto the server into one hidden file (`apps/api/.env`), by the person running the setup.

## The journey at a glance
1. Get a server (Hostinger VPS) · 2. Point the domain at it · 3. Put the code on it + run the setup ·
4. Paste your keys · 5. Build & turn it on · 6. Test · 7. Go live. **~half a day for a developer.**

---

## Step 1 — Get the server (Hostinger VPS)
> ⚠️ You need a Hostinger **VPS** ("KVM") plan — a normal web/shared hosting plan **will not run this app**.
> In hPanel, look for **VPS**. If you only see "Websites/Hosting," buy a VPS — **KVM 2** (2 CPU / 8 GB) is a
> good start.

1. hPanel → **VPS → Manage** (or buy a KVM VPS).
2. Operating system: choose **Ubuntu 24.04** (plain Ubuntu).
3. Set a **root password** and save it safely.
4. Note the server's **IP address** (e.g. `203.0.113.45`).

## Step 2 — Point your domain at the server
In your domain's DNS settings, add (replace with **your** IP):

| Type | Name | Value | Meaning |
|------|------|-------|---------|
| A | `@` | your IP | browsejobs.ai |
| A | `www` | your IP | www.browsejobs.ai |
| A | `api` | your IP | api.browsejobs.ai (backend) |

Set TTL to **300** if possible. DNS can take minutes to an hour.

## Step 3 — Put the code on the server & run setup
Open a terminal (Windows: PowerShell; Mac: Terminal) and log in:
```bash
ssh root@203.0.113.45        # your server IP; enter the root password
```
Then:
```bash
git clone https://github.com/krish-taurus/Browsejobs-lms.git /var/www/browsejobs
cd /var/www/browsejobs/deploy
chmod +x deploy.sh
sudo ./deploy.sh setup       # installs everything (~10–15 min)
```

## Step 4 — Plug in your accounts (the keys)
```bash
nano /var/www/browsejobs/apps/api/.env
```
(nano: paste after each `=`, then **Ctrl+O**, Enter to save, **Ctrl+X** to exit.)

- **AI (pick one):** Claude → `AI_PROVIDER=anthropic` + `ANTHROPIC_API_KEY` (console.anthropic.com).
  OpenAI → `AI_PROVIDER=openai` + `OPENAI_API_KEY` (platform.openai.com).
- **Razorpay:** Dashboard → Settings → API Keys → `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`. Webhook → add
  `https://api.browsejobs.ai/api/v1/webhooks/razorpay`, set `RAZORPAY_WEBHOOK_SECRET`, select payment events.
  (Start in **Test Mode**.)
- **Zoom:** marketplace.zoom.us → Develop → **Server-to-Server OAuth** → `ZOOM_ACCOUNT_ID`, `ZOOM_CLIENT_ID`,
  `ZOOM_CLIENT_SECRET`; Event Subscriptions webhook `…/webhooks/zoom` → `ZOOM_WEBHOOK_SECRET_TOKEN`. Your **2
  licenses**: assign them to users in Zoom admin, then map them to mentors in **Admin → Zoom licenses**.
- **Email (your main channel for now):** SMTP — `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
  `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS=hello@browsejobs.ai`.
- **File storage (simple start):** `FILESYSTEM_DISK=local` (files on the server; move to S3 later).
- **WhatsApp:** leave `WHATSAPP_*` blank — the app uses email/in-app until you add it.

## Step 5 — Build it and turn it on
Make sure Step 2's DNS is live first, then:
```bash
cd /var/www/browsejobs/deploy
sudo ./deploy.sh install
```
Live at **https://browsejobs.ai**. If the HTTPS step was skipped (DNS not ready), run the `certbot` command it
prints once DNS points at the server.

## Step 6 — Test before you announce
- Homepage loads with the padlock (https); `/courses` shows courses.
- `/admin` → create your **real** staff account (remove the demo `test@example.com`).
- Register a test student → confirm the **email** code arrives.
- Make a Razorpay **test** payment → confirm captured.
- Schedule a Zoom class → confirm join link + recording.

> ⚠️ Before real launch: create your own admin (remove the demo), and switch Razorpay to **Live** keys.

## Step 7 — Go live & maintain
- **Replacing an old site?** Change the domain's DNS to the new IP; keep the old server up for a couple of days
  as a safety net (see `cutover-runbook.md`).
- **Update later:** `cd /var/www/browsejobs/deploy && ./deploy.sh update`.
- **Add WhatsApp later:** paste its keys into `.env`, then `./deploy.sh update`.
- **Legal details (CIN/GST/refund window):** fill the `legal` block in `apps/web/src/content/landing.ts` once.

---

## For your developer (60-second version)
Laravel 11 API + Next.js 15, MySQL + Redis + queue worker. Deploy: fresh Ubuntu VPS → `git clone` →
`deploy/deploy.sh setup` → fill `apps/api/.env` → `deploy/deploy.sh install`; updates via
`deploy/deploy.sh update`. Full detail in `docs/deployment.md`, settings in `docs/env-checklist.md`, security
in `docs/launch-readiness-audit.md`, DNS switch in `docs/cutover-runbook.md`. This build: Zoom S2S with a
2-license pool (Admin → Zoom licenses), Razorpay + webhook, `AI_PROVIDER` anthropic|openai, WhatsApp env blank
(email active), `FILESYSTEM_DISK=local` to start. Rotate all keys; never commit `.env`.
