# Launch Runbook — browsejobs.ai
Your step-by-step to go live. Everything scripted lives in the repo already
(`deploy/deploy.sh`); these are only the steps that need a human.
Time needed: ~45 minutes active, plus DNS wait.

---

## STEP 0 — What you need before starting
- [ ] A VPS: Ubuntu 22.04 or 24.04, minimum 2 GB RAM / 2 vCPU (4 GB is comfy).
      DigitalOcean / Hetzner / AWS Lightsail all fine. Note its public IP.
- [ ] Access to your domain registrar (where browsejobs.ai is managed).
- [ ] Your API keys handy for later: Razorpay, Zoom, WhatsApp/Meta, AI provider
      key (Anthropic or OpenAI), SMTP. Missing ones can stay blank — features
      degrade gracefully.

## STEP 1 — Point DNS first (it needs time to propagate)
At your registrar, create three **A records**, all pointing to the VPS IP:

| Type | Name | Value |
|------|------|-------|
| A | `@` | YOUR_VPS_IP |
| A | `www` | YOUR_VPS_IP |
| A | `api` | YOUR_VPS_IP |

Do this FIRST — by the time you finish step 4, DNS will be ready for SSL.

## STEP 2 — Log in to the VPS
From your laptop's terminal (Mac/Linux) or PowerShell (Windows):
```bash
ssh root@YOUR_VPS_IP
```
(Your VPS provider gave you the root password or an SSH key at creation.)

## STEP 3 — Give the server access to the repo (one-time, 2 minutes)
The repo is private, so the server needs a key GitHub trusts:
```bash
ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519
cat ~/.ssh/id_ed25519.pub
```
Copy the line it prints. Then on GitHub:
**Repo → Settings → Deploy keys → Add deploy key** → paste it, name it
`vps`, leave "write access" UNCHECKED → Add.

Now clone:
```bash
sudo mkdir -p /var/www/browsejobs && sudo chown $USER /var/www/browsejobs
git clone git@github.com:krish-taurus/Browsejobs-lms.git /var/www/browsejobs
cd /var/www/browsejobs
```

## STEP 4 — Run setup (installs everything, ~10 min)
```bash
sudo bash deploy/deploy.sh setup
```
This installs PHP 8.3, MySQL, Redis, Node 20, nginx; creates the database
with a generated password; and writes both `.env` files with the right
domains already filled in.

## STEP 5 — Paste your secret keys (the only manual edit)
```bash
nano /var/www/browsejobs/apps/api/.env
```
Fill in what you have (full list with explanations: `docs/env-checklist.md`):
- `AI_PROVIDER=` + `ANTHROPIC_API_KEY=` (or your chosen provider)
- `RAZORPAY_KEY_ID=` / `RAZORPAY_KEY_SECRET=` / `RAZORPAY_WEBHOOK_SECRET=`
- Zoom + WhatsApp keys (can wait until after launch)
- SMTP mail settings
Save: `Ctrl+O`, `Enter`, `Ctrl+X`.

## STEP 6 — Install & go live (~10 min)
```bash
sudo bash deploy/deploy.sh install
```
Builds the API and the site, seeds first-run data, starts the services,
configures nginx, and gets the SSL certificates (needs Step 1's DNS live —
if certbot complains, wait 30 minutes and re-run the certbot line it prints).

**Verify:** open https://browsejobs.ai and https://api.browsejobs.ai/api/v1/market-intel

## STEP 7 — Turn on push-to-deploy (5 minutes, once)
So every merge to main deploys itself (the workflow is already in the repo):

1. On the VPS: `cat ~/.ssh/id_ed25519` — copy the WHOLE private key block.
   (This key stays between GitHub and your server only.)
2. On GitHub: **Repo → Settings → Secrets and variables → Actions →
   New repository secret**, three times:
   - `SSH_HOST` → your VPS IP
   - `SSH_USER` → `root` (or your sudo user)
   - `SSH_KEY` → the private key you copied
3. Add the server to its own trusted keys (lets Actions in):
   ```bash
   cat ~/.ssh/id_ed25519.pub >> ~/.ssh/authorized_keys
   ```
4. Test it: GitHub → **Actions → Deploy → Run workflow**. Green = every
   future push to main goes live automatically.

## STEP 8 — After-launch checklist
- [ ] Create your real admin user; disable the seeded demo admin
      (`docs/deployment.md` § first-run).
- [ ] Point Razorpay/Zoom/WhatsApp webhooks at `https://api.browsejobs.ai/...`
      (exact paths in `docs/env-checklist.md`).
- [ ] Search Console: browsejobs.ai property is already verified via the meta
      tag; submit `https://browsejobs.ai/sitemap.xml`.
- [ ] Update social profile photos with `browsejobs-social-avatar-400.png`
      from the logo kit.

## If something breaks
```bash
systemctl status browsejobs-web browsejobs-worker   # are services up?
journalctl -u browsejobs-web -n 50                  # web logs
tail -50 /var/www/browsejobs/apps/api/storage/logs/laravel.log
```
Copy whatever error you see into Claude Code and it'll take it from there.
