# deploy/ — production infra + one-script deploy

## The fast path: `deploy.sh`

On a fresh Ubuntu 22.04/24.04 VPS, this automates the whole setup. Three commands:

```bash
# 1. Install everything + generate the .env files (domain/DB pre-wired)
sudo ./deploy.sh setup

# 2. Fill the SECRETS in apps/api/.env  (AI key, Razorpay, Zoom, WhatsApp, mail, S3)
#    — see docs/env-checklist.md. Unused integrations can stay blank.

# 3. Build, migrate, seed, start services, and get TLS certificates
sudo ./deploy.sh install
```

Every later update is one command:

```bash
./deploy.sh update      # git pull → rebuild → migrate → restart services
```

**Before `install`,** point your domain's DNS (`browsejobs.ai`, `www`, `api.browsejobs.ai`) at the
server so the TLS step can verify. If DNS isn't ready yet, `install` still finishes and prints the one
`certbot` command to run once DNS propagates.

**Edit the config block at the top of `deploy.sh`** if your domain, repo URL, or paths differ — it's a
handful of variables (`DOMAIN`, `APP_DIR`, `REPO_URL`, …). The script never contains secrets; the DB
password is generated for you.

> The `.sh` file must keep Unix (LF) line endings — the repo's `.gitattributes` enforces this. If you
> copied it to the server another way and get a `bad interpreter` error, run: `sed -i 's/\r$//' deploy.sh`.

## What it uses (also usable by hand)

| File | Purpose |
|------|---------|
| `deploy.sh` | the setup/install/update automation above |
| `systemd/browsejobs-worker.service` | queue worker for the `high` (login codes) and `default` queues (restarted on every update) |
| `systemd/browsejobs-worker-bulk.service` | queue worker for the `bulk` queue: slow, high-volume jobs such as the weekly reports (restarted on every update) |
| `systemd/browsejobs-web.service` | the Next.js runtime |
| `nginx/api.conf` | `api.browsejobs.ai` → PHP-FPM |
| `nginx/web.conf` | `browsejobs.ai` + `www` → Next on :3000 |
| `systemd/browsejobs-taurus.service` | Taurus AI (`apps/taurus`) on :3100 |
| `nginx/taurus.conf` | `browsejobs.ai/taurusai` → Taurus AI (included into the web site) |

Prefer to run it by hand instead of the script? Every step is spelled out in **`docs/deploy-quickstart.md`**,
with the full explanation in **`docs/deployment.md`**.

## Taurus AI at `browsejobs.ai/taurusai`

`apps/taurus` is Taurus AI (AI Employees, workflows, the Workforce floor), a separate Next.js 14 app
with its own `package-lock.json` (not an npm workspace — the LMS web app is on Next 15 / React 19). It
runs as `browsejobs-taurus` on `:3100`, built with `basePath /taurusai`, and Nginx sends `/taurusai`
to it through `nginx/taurus.conf`. The older BrowseJobs Taurus client portal keeps
`/taurusai/app`, `/taurusai/claim/…`, `/taurusai/portal-login`, `/taurusai/recruitment` and
`/taurusai/hiring-demo` on the LMS web app.

**It stays off until configured.** `deploy.sh update` skips Taurus until
`apps/taurus/.env.production.local` exists, and a Taurus failure never fails or rolls back the LMS.
To turn it on, create the GitHub Actions secret **`TAURUS_ENV_FILE`** containing its env file as
`KEY=value` lines; the Deploy workflow writes it on every deploy and adds
`NEXT_PUBLIC_BASE_PATH=/taurusai` and `NEXT_PUBLIC_APP_URL=https://browsejobs.ai/taurusai` itself:

```
DATABASE_URL=postgres://…          # Postgres with pgvector (a Supabase project works)
NEXT_PUBLIC_SUPABASE_URL=https://<project>.supabase.co
NEXT_PUBLIC_SUPABASE_ANON_KEY=…
SUPABASE_SERVICE_ROLE_KEY=…        # server-only
AUTH_SECRET=…                      # long random string
TAURUS_MODEL_CREDENTIALS_MASTER_KEY=…
TAURUS_CHANNEL_CREDENTIALS_MASTER_KEY=…
TAURUS_WORKFLOWS_TICK_SECRET=…
OPENAI_API_KEY=…                   # or another provider key the Model Hub should use
```

See `apps/taurus/.env.example` for every option. In Supabase, add
`https://browsejobs.ai/taurusai/auth/callback` to the redirect URLs and set the Site URL to
`https://browsejobs.ai/taurusai`. Never set `TAURUS_ALLOW_DEV_AUTH` here — the workflow strips it.

On the first configured deploy, `deploy.sh` installs the systemd unit, the Nginx snippet
(`/etc/nginx/snippets/browsejobs-taurus.conf`) and one `include` line in the live
`browsejobs-web` site (it never overwrites the certbot-edited file, and restores it if `nginx -t`
fails). Migrations run from `apps/taurus/db/migrations`; knowledge uploads live in
`/var/lib/browsejobs-taurus/uploads`, outside the checkout. The build is capped at 1.5 GB of heap;
on the 4 GB droplet keep a swap file in place.


## Not included (by design)
Secrets (fill `.env` from `.env.example` / `docs/env-checklist.md`) and TLS certs (certbot generates them).
