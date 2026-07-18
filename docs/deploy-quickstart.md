# Deploy Quick-Start (commands only)

The fast path. Full explanations in `deployment.md`. Ubuntu 22.04/24.04, run as a sudo user.
Replace `browsejobs.ai`, `/var/www/browsejobs`, and the PHP-FPM socket to match your server.

## 1. Host packages
```bash
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.3-{cli,fpm,mysql,redis,mbstring,xml,curl,bcmath,intl,gd,zip} \
                    composer nginx mysql-server redis-server
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs
sudo snap install --classic certbot
```

## 2. Get the code
```bash
sudo mkdir -p /var/www/browsejobs && sudo chown -R $USER:www-data /var/www/browsejobs
git clone <repo-url> /var/www/browsejobs && cd /var/www/browsejobs
```

## 3. API (apps/api)
```bash
cd /var/www/browsejobs/apps/api
composer install --no-dev --optimize-autoloader
cp .env.example .env          # then fill it — see env-checklist.md
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # FIRST deploy only (roles, tenant, curriculum, demo)
php artisan storage:link
php artisan optimize          # config+route+event+view cache
```

## 4. Web (apps/web)
```bash
cd /var/www/browsejobs/apps/web
cp .env.example .env          # fill NEXT_PUBLIC_* — see env-checklist.md
npm ci && npm run build
```

## 5. Services (from repo `deploy/` templates)
```bash
cd /var/www/browsejobs
sudo cp deploy/systemd/browsejobs-worker.service /etc/systemd/system/
sudo cp deploy/systemd/browsejobs-web.service    /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now browsejobs-worker browsejobs-web
```

## 6. Scheduler cron
```bash
( crontab -l 2>/dev/null; \
  echo "* * * * * cd /var/www/browsejobs/apps/api && php artisan schedule:run >> /dev/null 2>&1" \
) | crontab -
```

## 7. Nginx + TLS
```bash
sudo cp deploy/nginx/api.conf /etc/nginx/sites-available/browsejobs-api
sudo cp deploy/nginx/web.conf /etc/nginx/sites-available/browsejobs-web
sudo ln -s /etc/nginx/sites-available/browsejobs-api /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/browsejobs-web /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d browsejobs.ai -d www.browsejobs.ai -d api.browsejobs.ai
```

## 8. Verify
```bash
systemctl status browsejobs-worker browsejobs-web   # both active (running)
curl -I https://api.browsejobs.ai/api/v1/branding    # 200
curl -I https://browsejobs.ai                        # 200
php artisan queue:failed                              # empty
```

## Redeploy (every time after the first)
```bash
cd /var/www/browsejobs && git pull
cd apps/api && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize
cd ../web && npm ci && npm run build
sudo systemctl restart browsejobs-worker browsejobs-web   # workers MUST restart to run new code
sudo systemctl reload php8.3-fpm
```

## Rollback
```bash
cd /var/www/browsejobs && git checkout <previous-tag>
cd apps/api && composer install --no-dev && php artisan migrate --force && php artisan optimize
cd ../web && npm ci && npm run build
sudo systemctl restart browsejobs-worker browsejobs-web
# DNS rollback (300s TTL) + DB snapshot restore: see cutover-runbook.md
```
