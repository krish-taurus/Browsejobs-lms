# Cutover Runbook — browsejobs.ai PHP → LMS (P4.10)

Replacing the current PHP site at **browsejobs.ai** with the new Next.js public site + LMS, with **zero
SEO loss and zero downtime**. Work top-to-bottom; the DNS switch is the last, reversible step.

---

## 1. Crawl the current site → build the URL map

The 301 map lives in code at `apps/web/src/lib/redirects.ts` (`legacyRedirects`), already seeded with
the predictable `.php` patterns. **Complete it from a real crawl so nothing 404s:**

1. Crawl the live PHP site (read-only):
   ```
   # Sitemap + link crawl of every indexable URL
   wget --spider --recursive --no-parent --level=10 https://browsejobs.ai 2>&1 \
     | grep -Eo 'https?://browsejobs\.ai[^ ]+' | sort -u > legacy-urls.txt
   ```
   Also pull Google Search Console → **Pages → Indexed** and Bing Webmaster for URLs the crawler misses
   (old campaign landing pages, PDFs, deep links from ads).
2. For each legacy URL, decide its new home:
   - 1:1 content match → map to the new route.
   - No direct equivalent → map to the nearest parent (`/courses`, or `/` for retired pages) — **never**
     leave it to 404.
   - Retired campaign URLs with backlinks → still 301 (link equity), usually to `/register`.
3. Add every mapping to `legacyRedirects` as `{ source, destination, permanent: true }`. Host
   canonicalisation (`browsejobs.in` and `www.*` → apex `browsejobs.ai`) is already handled by
   `hostRedirects`. Redeploy; the redirects ship with the app (verified at build).

**Acceptance:** every URL in `legacy-urls.txt` returns `200` or `301` (never `404`/`500`) on staging —
see §4.

---

## 2. SEO parity check (titles, metas, schema)

The new public pages already emit metadata, OpenGraph, JSON-LD (`Course` schema), and a sitemap (P1.7).
Before cutover, verify parity **per mapped URL**:

| Signal | Old (PHP) | New | Check |
|--------|-----------|-----|-------|
| `<title>` | record | record | New title ≥ as descriptive; brand suffix consistent |
| `meta description` | record | record | Present, unique, ≤160 chars |
| `<h1>` | record | record | One per page, keyword-aligned |
| Canonical | record | new absolute URL | Self-canonical, apex host |
| OG/Twitter | record | present | Image resolves, title/desc set |
| JSON-LD | (likely none) | `Course` / `Organization` | Validates in the Rich Results test |
| Sitemap | old | `/sitemap.xml` | Lists all new public URLs, submitted to GSC |

- Confirm `robots.txt` allows the public site and points at the new sitemap.
- Keep the **primary tagline / positioning** consistent (§14.1) so brand queries still match.
- Run Lighthouse on `/`, `/courses`, a `/courses/[slug]` — meet the budget: **LCP < 2.5s, CLS < 0.1**
  (ad traffic converts on speed).

---

## 3. Staging sign-off checklist

Stand up staging on the real stack (VPS + MySQL + Redis + Horizon) with **test-mode** keys:

- [ ] Razorpay **test mode**; Zoom **sandbox**; WhatsApp test number; AI provider key with a low budget.
- [ ] `feed:sync`, reminders, digests, and all scheduled commands run under supervised cron/Horizon.
- [ ] Full journey witnessed end-to-end: **ad link → masterclass register → reminder → join Zoom →
      attendance → pay EMI (test) → enrol → learn → mock → CV → apply → tracker.**
- [ ] §10 audit (`launch-readiness-audit.md`): all ⚙️ ops items done; gap #7 closed or accepted.
- [ ] Cross-tenant isolation spot-checked on staging data; DPDP access + deletion exercised once.
- [ ] Redirect map: `legacy-urls.txt` replayed against staging — 0 unexpected 404s (§4).
- [ ] Backups: DB snapshot + restore rehearsed; object storage (recordings/CVs) reachable.
- [ ] Error tracking + uptime monitor live; queue-failure alerting fires on a forced failure.

Sign-off = founder + build owner both tick the journey and the redirect replay.

---

## 4. Replay the redirect map (automated gate)

Before and after cutover, prove no dead links:
```
# Expect only 200 or 301 for every legacy URL
while read url; do
  code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: browsejobs.ai' "$STAGING$url")
  [ "$code" = 200 ] || [ "$code" = 301 ] || echo "BROKEN $code $url"
done < legacy-urls.txt
```
Any line printed is a missing mapping — add it to `legacyRedirects` and redeploy.

---

## 5. DNS cutover runbook

**Pre-cutover (T-24h):** lower the DNS TTL on the `browsejobs.ai` A/ALIAS record to **300s** so the
switch (and any rollback) propagates fast. Confirm the new host serves the site on a temporary hostname
with a valid TLS cert (HSTS on).

**Cutover (low-traffic window, e.g. 02:00 IST):**
1. Freeze content changes on the old site.
2. Final DB migration/seed check on prod; `php artisan migrate --force`; warm caches.
3. Point the apex `browsejobs.ai` record at the new host; keep `browsejobs.in` and `www` pointed so
   `hostRedirects` 301s them to the apex.
4. Verify TLS (apex + any subdomain), then replay §4 against **production**.
5. Submit the new `sitemap.xml` in GSC; use "Validate fix" on any prior coverage issues.

**Post-cutover (T+1h → T+72h):** watch error tracker, GSC coverage, and 404 logs; add any surfaced
legacy URL to the map. Confirm payment + Zoom webhooks arrive on the new host (signatures verify).

**Rollback plan:** because TTL is 300s, revert the apex record to the old host to restore the PHP site
within minutes. Triggers: checkout/payment broken, auth broken, or sustained 5xx. The old site stays
running and untouched until **72h** of clean metrics — do not decommission it before then. Keep a DB
snapshot from immediately pre-cutover for a data rollback if needed.

---

## 6. Post-launch

- Rotate every key again if any staging key touched a shared channel (§10 item 1).
- Decommission the PHP host after 72h clean; keep a static archive of it for reference.
- Monitor GSC for 30 days; a temporary ranking dip is normal — the 301s recover it.
