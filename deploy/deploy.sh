#!/usr/bin/env bash
# =============================================================================
# BrowseJobs LMS — one-script deploy for a fresh Ubuntu 22.04/24.04 VPS.
#
# Three commands, in order:
#   sudo ./deploy.sh setup     # install everything + create the .env files
#   #  ... then fill the SECRET values in apps/api/.env (keys/passwords) ...
#   sudo ./deploy.sh install   # build, migrate, seed, start services, TLS
#   #  ... later, for every update:
#   ./deploy.sh update
#
# It never contains secrets — you paste those into .env between `setup` and
# `install`. The DB password is generated for you.
# =============================================================================
set -euo pipefail

# ---- Config (edit these if your domain / paths differ) ----------------------
DOMAIN="browsejobs.ai"
API_HOST="api.${DOMAIN}"
APP_DIR="/var/www/browsejobs"
REPO_URL="https://github.com/krish-taurus/Browsejobs-lms.git"
DB_NAME="browsejobs"
DB_USER="browsejobs"
PHP_FPM_SOCK="/run/php/php8.3-fpm.sock"
# -----------------------------------------------------------------------------

API_DIR="${APP_DIR}/apps/api"
WEB_DIR="${APP_DIR}/apps/web"
say(){ printf "\n\033[1;34m▸ %s\033[0m\n" "$*"; }
ok(){ printf "\033[1;32m✓ %s\033[0m\n" "$*"; }
die(){ printf "\033[1;31m✗ %s\033[0m\n" "$*" >&2; exit 1; }

need_root(){ [ "$(id -u)" = "0" ] || die "Run with sudo: sudo ./deploy.sh $1"; }

cmd_setup(){
  need_root setup
  say "Installing system packages (PHP 8.3, Node 20, MySQL, Redis, Nginx)…"
  add-apt-repository -y ppa:ondrej/php
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get update -y
  apt-get install -y php8.3-{cli,fpm,mysql,redis,mbstring,xml,curl,bcmath,intl,gd,zip} \
                     composer nginx mysql-server redis-server nodejs git certbot python3-certbot-nginx
  systemctl enable --now php8.3-fpm mysql redis-server nginx
  ok "Packages installed."

  say "Getting the code into ${APP_DIR}…"
  if [ ! -d "${APP_DIR}/.git" ]; then
    mkdir -p "${APP_DIR}"; git clone "${REPO_URL}" "${APP_DIR}"
  else
    git -C "${APP_DIR}" pull --ff-only
  fi
  ok "Code in place."

  say "Creating the database and a scoped user…"
  DB_PASS="$(openssl rand -hex 20)"
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
  ok "Database '${DB_NAME}' ready."

  say "Writing apps/api/.env with production + domain defaults…"
  if [ ! -f "${API_DIR}/.env" ]; then cp "${API_DIR}/.env.example" "${API_DIR}/.env"; fi
  api_set(){ grep -q "^$1=" "${API_DIR}/.env" && sed -i "s|^$1=.*|$1=$2|" "${API_DIR}/.env" || echo "$1=$2" >> "${API_DIR}/.env"; }
  api_set APP_ENV production
  api_set APP_DEBUG false
  api_set APP_URL "https://${API_HOST}"
  api_set FRONTEND_URL "https://${DOMAIN}"
  api_set APP_TIMEZONE Asia/Kolkata
  api_set SESSION_DOMAIN ".${DOMAIN}"
  api_set SANCTUM_STATEFUL_DOMAINS "${DOMAIN},www.${DOMAIN}"
  api_set FRONTEND_ORIGINS "https://${DOMAIN},https://www.${DOMAIN}"
  api_set DB_CONNECTION mysql
  api_set DB_HOST 127.0.0.1
  api_set DB_PORT 3306
  api_set DB_DATABASE "${DB_NAME}"
  api_set DB_USERNAME "${DB_USER}"
  api_set DB_PASSWORD "${DB_PASS}"
  api_set SESSION_DRIVER redis
  api_set QUEUE_CONNECTION redis
  api_set CACHE_STORE redis
  ok "apps/api/.env written (DB password generated & wired)."

  say "Writing apps/web/.env…"
  if [ ! -f "${WEB_DIR}/.env" ]; then cp "${WEB_DIR}/.env.example" "${WEB_DIR}/.env"; fi
  web_set(){ grep -q "^$1=" "${WEB_DIR}/.env" && sed -i "s|^$1=.*|$1=$2|" "${WEB_DIR}/.env" || echo "$1=$2" >> "${WEB_DIR}/.env"; }
  web_set NEXT_PUBLIC_API_URL "https://${API_HOST}"
  web_set NEXT_PUBLIC_SITE_URL "https://${DOMAIN}"
  web_set NEXT_PUBLIC_DEFAULT_TENANT browsejobs
  web_set REVALIDATE_SECRET "$(openssl rand -hex 16)"
  ok "apps/web/.env written."

  cat <<NEXT

  ────────────────────────────────────────────────────────────────────────
  SETUP DONE. Now fill the SECRETS in:  ${API_DIR}/.env
    · AI provider key (ANTHROPIC_API_KEY or OPENAI_API_KEY) + AI_PROVIDER
    · Razorpay (KEY_ID / KEY_SECRET / WEBHOOK_SECRET)
    · Zoom, WhatsApp, mail (SMTP), S3 storage (AWS_*)  — see docs/env-checklist.md
  Unused integrations can stay blank. Then run:

      sudo ./deploy.sh install
  ────────────────────────────────────────────────────────────────────────
NEXT
}

cmd_install(){
  need_root install
  say "API: dependencies, key, migrate, seed, optimize…"
  cd "${API_DIR}"
  sudo -u www-data composer install --no-dev --optimize-autoloader
  grep -q "^APP_KEY=base64:" .env || php artisan key:generate --force
  php artisan migrate --force
  if [ "$(php artisan tinker --execute='echo \App\Models\Tenant::count();' 2>/dev/null | tail -1)" = "0" ]; then
    php artisan db:seed --force
    ok "Seeded starter data (roles, tenant, courses, demo)."
  fi
  php artisan storage:link || true
  php artisan optimize
  chown -R www-data:www-data "${API_DIR}/storage" "${API_DIR}/bootstrap/cache"
  ok "API ready."

  say "Web: install & build…"
  # Install from the repo ROOT — the lockfile + npm workspaces live there, so
  # `next` and other binaries are hoisted correctly (a per-package `npm ci`
  # inside apps/web has no lockfile and leaves `next` unlinked → "next: not found").
  cd "${APP_DIR}"; npm ci; npm run build --workspace @browsejobs/web; ok "Web built."

  say "Installing services (worker + web) + scheduler cron…"
  sed "s#/var/www/browsejobs#${APP_DIR}#g" "${APP_DIR}/deploy/systemd/browsejobs-worker.service" >/etc/systemd/system/browsejobs-worker.service
  sed "s#/var/www/browsejobs#${APP_DIR}#g" "${APP_DIR}/deploy/systemd/browsejobs-web.service"    >/etc/systemd/system/browsejobs-web.service
  systemctl daemon-reload
  systemctl enable --now browsejobs-worker browsejobs-web
  # `crontab -l` exits 1 when root has no crontab yet (fresh server) — the
  # `|| true` keeps set -e/pipefail from silently killing the script here.
  CRON_KEEP="$(crontab -l 2>/dev/null | grep -v 'schedule:run' || true)"
  printf '%s\n%s\n' "${CRON_KEEP}" "* * * * * cd ${API_DIR} && php artisan schedule:run >> /dev/null 2>&1" \
    | sed '/^$/d' | crontab -
  ok "Worker + web running; scheduler cron installed."

  say "Configuring Nginx…"
  sed -e "s#/var/www/browsejobs#${APP_DIR}#g" -e "s#api.browsejobs.ai#${API_HOST}#g" -e "s#php8.3-fpm.sock#$(basename ${PHP_FPM_SOCK})#g" \
      "${APP_DIR}/deploy/nginx/api.conf" >/etc/nginx/sites-available/browsejobs-api
  sed -e "s#browsejobs.ai#${DOMAIN}#g" "${APP_DIR}/deploy/nginx/web.conf" >/etc/nginx/sites-available/browsejobs-web
  ln -sf /etc/nginx/sites-available/browsejobs-api /etc/nginx/sites-enabled/
  ln -sf /etc/nginx/sites-available/browsejobs-web /etc/nginx/sites-enabled/
  nginx -t && systemctl reload nginx
  ok "Nginx configured."

  say "Getting TLS certificates (make sure DNS points here first)…"
  certbot --nginx --non-interactive --agree-tos -m "hello@${DOMAIN}" \
    -d "${DOMAIN}" -d "www.${DOMAIN}" -d "${API_HOST}" || \
    printf "\033[1;33m! TLS step skipped — point DNS at this server, then run: sudo certbot --nginx -d %s -d www.%s -d %s\033[0m\n" "${DOMAIN}" "${DOMAIN}" "${API_HOST}"

  cat <<DONE

  \033[1;32m✓ INSTALL COMPLETE.\033[0m
  Verify:  systemctl status browsejobs-worker browsejobs-web
           curl -I https://${API_HOST}/api/v1/branding
           curl -I https://${DOMAIN}
  Admin login: https://${DOMAIN}/admin  (create real staff users; the seeded
  test admin is for local dev only — change/remove it in production).
DONE
}

cmd_update(){
  say "Updating from git…"
  git -C "${APP_DIR}" pull --ff-only
  cd "${API_DIR}"; sudo -u www-data composer install --no-dev --optimize-autoloader
  php artisan migrate --force; php artisan optimize
  cd "${APP_DIR}"; npm ci; npm run build --workspace @browsejobs/web
  say "Refreshing systemd units (picks up worker timeout/flag changes)…"
  sed "s#/var/www/browsejobs#${APP_DIR}#g" "${APP_DIR}/deploy/systemd/browsejobs-worker.service" >/etc/systemd/system/browsejobs-worker.service
  sed "s#/var/www/browsejobs#${APP_DIR}#g" "${APP_DIR}/deploy/systemd/browsejobs-web.service"    >/etc/systemd/system/browsejobs-web.service
  systemctl daemon-reload
  say "Restarting services (worker MUST restart to run new code)…"
  systemctl restart browsejobs-worker browsejobs-web
  systemctl reload php8.3-fpm || true
  ok "Update complete."
}

case "${1:-}" in
  setup)   cmd_setup ;;
  install) cmd_install ;;
  update)  cmd_update ;;
  *) echo "Usage: sudo ./deploy.sh {setup|install|update}"; exit 1 ;;
esac
