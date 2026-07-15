# BrowseJobs LMS

Unified AI-driven LMS + Education CRM for IBrowseJobs Technologies. Multi-tenant,
whitelabel-ready. Full lifecycle: Ad → Masterclass → Bootcamp → Paid Batch →
Learning → Placement.

> **Read [`CLAUDE.md`](./CLAUDE.md) and [`docs/browsejobs-lms-requirements.md`](./docs/browsejobs-lms-requirements.md) (PRD) before any task.** The PRD is the single source of truth.

## Monorepo layout

```
/apps/api         Laravel 11 (PHP 8.3) — REST API, queues, webhooks
/apps/web         Next.js 15 + TypeScript — public site + portals
/packages/shared  Shared TypeScript DTOs (single source for API types)
/docker           Local dev stack (MySQL 8, Redis, Mailpit, MinIO)
/docs             PRD, build playbook, ADRs
```

## Prerequisites

- PHP **8.3** + Composer 2
- Node **20+** (repo built/verified on Node 24) + npm
- Docker Desktop (for the local infrastructure stack)

## Quickstart

```bash
# 1. Infrastructure
docker compose -f docker/docker-compose.yml up -d

# 2. API (http://localhost:8000)
cd apps/api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve

# 3. Web (http://localhost:3000) — from repo root
npm install
npm run dev
```

Copy every `.env.example` to `.env` and fill in real values locally — **never
commit secrets** (see CLAUDE.md Security Guardrails).

## Commands

| Command | Where | What |
|---|---|---|
| `composer test` | `apps/api` | Pest test suite |
| `composer lint` | `apps/api` | Laravel Pint (format) |
| `composer lint:test` | `apps/api` | Pint in check mode (CI) |
| `npm run typecheck` | root | `tsc --noEmit` across JS workspaces |
| `npm run build` | root | Build all JS workspaces |
| `npm run test:e2e` | root / `apps/web` | Playwright e2e |
| `docker compose -f docker/docker-compose.yml up -d` | root | Local stack |

CI (`.github/workflows/ci.yml`) runs API lint+tests and web typecheck+build+e2e
on every push/PR to `main`/`master`.

## Build order

Feature work follows [`docs/BUILD-PLAYBOOK.md`](./docs/BUILD-PLAYBOOK.md) — one
prompt per session, one commit. This scaffold is playbook step **P1.1**.
