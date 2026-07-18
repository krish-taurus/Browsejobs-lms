# Deployment & Infrastructure Guide — BrowseJobs LMS

How to stand up **staging** and **production** for the Laravel 11 API + Next.js 15 web app. Templates
for Nginx, systemd, and cron live in `deploy/`. Pair this with `launch-readiness-audit.md` (the §10
gate) and `cutover-runbook.md` (the DNS switch).

> ⚠️ This stack (Laravel queues + Redis + Next.js + webhooks) does **not** run on shared hosting. Use a
> VPS (Hostinger VPS / DigitalOcean / Hetzner).

---

## 1. Server sizing

Per the §10 load target (500 concurrent students · 20 live classes · 1,000 masterclass registrants):

| Env | Spec (start) | Notes |
|-----|--------------|-------|
| Staging | 2 vCPU / 4 GB / 40 GB SSD | Single box; all services co-located. |
| Production | 4 vCPU / 8 GB / 80 GB SSD (scale from load-test) | Consider splitting DB + Redis to their own box as traffic grows. |

Managed **MySQL 8** and **Redis** (e.g. the provider's DBaaS) are recommended in production over
self-hosted — they handle backups, failover, and patching. Object storage: an S3-compatible bucket
(AWS S3 Mumbai `ap-south-1`, DigitalOcean Spaces, or self-hosted MinIO) for recordings, CVs, and
certificates.

---

## 2. Host prerequisites

Ubuntu 22.04/24.04 LTS:

```
# PHP 8.3 + extensions
sudo add-apt-repository ppa:ondrej/php && sudo apt update
sudo apt install -y php8.3-{cli,fpm,mysql,redis,mbstring,xml,curl,bcmath,intl,gd,zip}

# Node 20 (for the Next.js build/runtime)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs

# Composer, Nginx, certbot
sudo apt install -y composer nginx
sudo snap install --classic certbot
```

Managed MySQL 8 + Redis, or install locally (`mysql-server`, `redis-server`). Judge0 (coding labs) is a
separate service — run it via `docker/docker-compose.yml` (the `judge0-*` services) on its own box or a
managed instance and point `JUDGE0_URL` at it.

---

## 3. Application deploy (API — `apps/api`)

```
cd /var/www/browsejobs && git clone <repo> . && cd apps/api
composer install --no-dev --optimize-autoloader
cp .env.example .env            # then fill real values (§6) — NEVER commit .env
php artisan key:generate
php artisan migrate --force     # --force: no prompt in production
php artisan db:seed --force     # first deploy only (roles, default tenant, curriculum)
php artisan storage:link
php artisan config:cache route:cache event:cache view:cache
```

Point PHP-FPM's web root at `apps/api/public`. On every redeploy: `git pull`, `composer install
--no-dev`, `migrate --force`, re-run the `*:cache` commands (or `php artisan optimize`), then reload
FPM and restart the queue workers (§4).

---

## 4. Queue workers (side effects — mandatory)

Every AI call, message send, PDF render, Zoom/webhook action is a queued Redis job. **Nothing works
without a running worker.** Run it under systemd — template in `deploy/systemd/browsejobs-worker.service`:

```
sudo cp deploy/systemd/browsejobs-worker.service /etc/systemd/system/
sudo systemctl enable --now browsejobs-worker
# scale: run 2–4 workers (browsejobs-worker@1 … @4) via a templated unit under load
```

The worker runs `php artisan queue:work redis --tries=3 --max-time=3600 --backoff=10`. Jobs are
idempotent and retry-safe. **On deploy, restart workers** (`systemctl restart browsejobs-worker`) so
they pick up new code — or the running worker keeps executing the old release.

> **Optional upgrade:** install `laravel/horizon` for a queue dashboard + metrics (CLAUDE.md's
> recommendation). Then replace `queue:work` with `php artisan horizon` in the unit and add
> `config/horizon.php`. `HORIZON_PREFIX` is already reserved in `.env.example`.

**Failure alerting:** watch failed jobs — `php artisan queue:failed` — and wire an alert (a cron that
pages if `failed_jobs` grows, or Horizon's notifications). A silently-dead worker is the #1 launch risk.

---

## 5. Scheduler (cron)

One cron line drives all 19 scheduled commands (they self-gate by time):

```
* * * * * cd /var/www/browsejobs/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

What runs (defined in `routes/console.php`):

| Command | Cadence | Purpose |
|---------|---------|---------|
| `fees:run-ladder` | daily 07:00 | Fee reminder/escalation ladder |
| `conversions:run-nudges` | daily 08:00 | Bootcamp→paid nudges |
| `support:check-sla` | hourly | Ticket SLA warnings/escalation |
| `scores:recompute` | daily 06:00 | Rule-based student rescore + snapshots |
| `tutor:reindex` | weekly Mon 05:00 | AI-tutor knowledge reindex |
| `quizzes:check-dispatch` | hourly | MCQ dispatch reminders |
| `reports:weekly` | weekly Mon 07:30 | Weekly student AI reports |
| `digest:counselor-daily` | daily 06:30 | Counselor risk digest |
| `digest:support-themes` | weekly Mon 08:00 | Support themes to admin |
| `digest:market-pulse` | daily 06:45 | Market Pulse build |
| `pulse:weekly-send` | weekly Mon 10:00 | Market Pulse opt-in send |
| `care:dispatch` | daily 09:00 | Retention/NPS dispatch |
| `care:signals` | daily 06:15 | Rage-signal detection |
| `curriculum:recommend` | quarterly | Syllabus recommendation reports |
| `alumni:checkins` | daily 07:15 | Alumni 6/12-mo check-ins |
| `pri:calibrate` | monthly 1st 05:30 | PRI weight calibration |
| `feed:sync` | twice daily 06/18 | Job-feed sync + expiry |
| `jobs:nudge` | daily 09:30 | "Jobs for You" match nudge |
| `applications:follow-ups` | daily 10:00 | Application follow-up nudges |

Set the server timezone to **Asia/Kolkata** (or keep UTC and note the offset — `APP_TIMEZONE` drives
in-app times).

---

## 6. Environment variables

Fill `apps/api/.env` from `.env.example` (placeholder names only in the repo). Groups: app (`APP_KEY`,
`APP_URL`, `FRONTEND_URL`), DB, Redis, session/queue/cache (all `redis`/`s3` in prod), **Sanctum**
(`SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_ORIGINS` — the web app's real host, for cookie auth),
`FILESYSTEM_DISK=s3` + `AWS_*`, mail (SMTP), and the integration keys (Razorpay, Zoom, WhatsApp, AI
provider, Judge0, Vapi). Admin-entered integration keys in the Settings UI **override** env and are
encrypted at rest (ADR 0039).

`apps/web/.env`: `NEXT_PUBLIC_API_URL` (the API's public `/api/v1`), `NEXT_PUBLIC_SITE_URL`,
`NEXT_PUBLIC_DEFAULT_TENANT`, `REVALIDATE_SECRET`.

**Rotate every key into the secret store before go-live** (§10 item 1) — never reuse anything pasted in
a chat.

---

## 7. Web app (Next.js — `apps/web`)

```
cd apps/web && npm ci && npm run build
# runtime: `npm run start` (next start) on port 3000, behind Nginx
sudo cp deploy/systemd/browsejobs-web.service /etc/systemd/system/
sudo systemctl enable --now browsejobs-web
```

The public pages are statically rendered + SEO-complete; the 301 cutover redirects ship in
`next.config.ts` (P4.10). Rebuild on every deploy.

---

## 8. Nginx + TLS

Two server blocks (templates in `deploy/nginx/`): the **API** (`api.<domain>` → PHP-FPM on
`apps/api/public`) and the **web app** (`<domain>` + `www` → reverse-proxy to Next on `:3000`).

```
sudo cp deploy/nginx/api.conf /etc/nginx/sites-available/browsejobs-api
sudo cp deploy/nginx/web.conf /etc/nginx/sites-available/browsejobs-web
sudo ln -s ../sites-available/browsejobs-{api,web} /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d browsejobs.ai -d www.browsejobs.ai -d api.browsejobs.ai
```

TLS everywhere (§10 item 6): HSTS on, HTTP→HTTPS redirect (certbot adds it). The API and web app must
share a **parent domain** so the Sanctum session cookie works across them (`SESSION_DOMAIN=.browsejobs.ai`).

---

## 9. Webhooks

Point each provider's webhook at the API and confirm signatures verify (§10 item 2):
Razorpay → `https://api.browsejobs.ai/api/v1/webhooks/razorpay`, Zoom → `/webhooks/zoom`, WhatsApp →
`/webhooks/whatsapp`, Meta Lead Ads → `/webhooks/meta-lead`. Set each provider's signing secret in env.

---

## 10. Backups, monitoring, rollback

- **Backups:** nightly MySQL dump + object-storage lifecycle/versioning; rehearse a restore before
  launch.
- **Monitoring:** an uptime check on the web app + API health, error tracking (Sentry or the log
  stack), and queue-depth/failed-job alerting (§4).
- **Zero-downtime-ish deploy:** build the web app and run migrations, reload FPM, restart workers +
  web. For true zero-downtime, deploy to a release dir and switch a symlink.
- **Rollback:** keep the previous release + a pre-deploy DB snapshot; `git checkout` the prior tag,
  re-run `optimize`, restart services. DNS rollback (300s TTL) is in `cutover-runbook.md`.

---

## 11. Go-live checklist

Work `launch-readiness-audit.md` §10 to green, then: keys rotated · workers + scheduler running ·
webhooks verified · TLS + HSTS · backups rehearsed · load test passed (`cutover-runbook.md`) · the
witnessed **ad-link → placement** demo on staging. Then cut DNS per the cutover runbook.
