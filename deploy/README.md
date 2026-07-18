# deploy/ — production infra templates

Copy-and-fill templates referenced by `docs/deployment.md`. **Adjust paths, domains, and the PHP-FPM
socket** to your server before use.

```
deploy/
├── nginx/
│   ├── api.conf     # api.browsejobs.ai → PHP-FPM (Laravel public/)
│   └── web.conf     # browsejobs.ai + www → reverse-proxy to Next on :3000
└── systemd/
    ├── browsejobs-worker.service   # queue:work (RESTART on every deploy)
    └── browsejobs-web.service      # next start
```

**Not included (by design):** the cron line for the scheduler (a one-liner in `docs/deployment.md` §5),
TLS certs (certbot generates them), and any `.env` (secrets live only in the server's secret store —
fill from `apps/*/.env.example`). Install order and the full walkthrough are in `docs/deployment.md`.
