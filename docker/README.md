# Local Dev Stack

Local infrastructure for BrowseJobs LMS. Requires Docker Desktop.

## Start / stop

```bash
# from the repo root
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml ps
docker compose -f docker/docker-compose.yml down          # stop
docker compose -f docker/docker-compose.yml down -v       # stop + wipe volumes
```

## Services

| Service | Purpose | Host port | UI |
|---|---|---|---|
| `mysql` | MySQL 8 (app database) | `3306` | — |
| `redis` | Redis 7 (cache, queues, Horizon) | `6379` | — |
| `mailpit` | SMTP catcher for local email | `1025` (SMTP) | http://localhost:8025 |
| `minio` | S3-compatible object storage | `9000` (API) | http://localhost:9001 |

Credentials and hostnames match `apps/api/.env.example`. MinIO console login:
`minioadmin` / `changeme`. A `browsejobs` bucket is created automatically on
first `up` by the `minio-createbucket` one-shot service.

> ⚠️ All passwords in `docker-compose.yml` are **local dev placeholders**. Never
> reuse them in staging or production (see CLAUDE.md Security Guardrails).

## Point the API at the stack

```bash
cd apps/api
cp .env.example .env      # then set APP_KEY via: php artisan key:generate
php artisan migrate
```
