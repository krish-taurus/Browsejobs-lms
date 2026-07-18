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
| `systemd/browsejobs-worker.service` | queue worker (restarted on every update) |
| `systemd/browsejobs-web.service` | the Next.js runtime |
| `nginx/api.conf` | `api.browsejobs.ai` → PHP-FPM |
| `nginx/web.conf` | `browsejobs.ai` + `www` → Next on :3000 |

Prefer to run it by hand instead of the script? Every step is spelled out in **`docs/deploy-quickstart.md`**,
with the full explanation in **`docs/deployment.md`**.

## Not included (by design)
Secrets (fill `.env` from `.env.example` / `docs/env-checklist.md`) and TLS certs (certbot generates them).
