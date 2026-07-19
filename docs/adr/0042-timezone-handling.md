# ADR 0042 — Timestamps normalize to the app timezone at every boundary

- **Status:** Accepted
- **Date:** 2026-07-19
- **PRD:** §6.3 (live classes), §6.11 (mentoring)
- **Relates to:** ADR 0040 (Zoom license pool), ADR 0032 (mentor scheduling)

## Context

`.env.example` sets `APP_TIMEZONE=Asia/Kolkata`, but the live-class and mentoring code was
written and verified under UTC. Eloquent's `datetime` cast stores a Carbon's **own wall
time** and reads it back in the **app timezone**, so a UTC-zoned instance (a Zoom webhook
`join_time`, a client-sent ISO `starts_at`) saved under an IST app timezone silently shifts
by the 5½-hour offset on read-back. Concretely: webhook attendance durations computed 6 hours
for a 30-minute class, mentor bookings landed on the wrong slot, and 5 tests failed on a
fresh checkout with the shipped `.env`.

## Decision

1. **`App\Support\Time\AppTime::parse()` at every ingress.** Any timestamp arriving from
   outside — Zoom webhook payloads, API request bodies, admin scheduling forms — is parsed
   and converted to `config('app.timezone')` **before** it touches a model. With the Carbon
   in the app zone, the cast's naive store/read round-trip is exact under any `APP_TIMEZONE`.
   Applied to: `ZoomWebhookController`, `MentorBookingController` (book + reschedule),
   `LiveSessionController` (schedule + reschedule), `CrmTaskController` (due_at).

2. **API output serializes datetimes in UTC.** Mentoring `starts_at` responses use
   `->utc()->toIso8601String()`, so the wire contract is stable (`+00:00`) regardless of the
   server timezone. The `.ics` endpoint already emitted UTC (`Z`) and is unchanged.

3. **Test fixtures are timezone-explicit.** Fixtures that pair with UTC webhook payloads or
   UTC wire expectations construct values via `AppTime::parse('…Z')` instead of naive
   strings, and `Carbon::setTestNow()` receives zone-aware instants. The suite passes under
   both `APP_TIMEZONE=UTC` and `Asia/Kolkata`; `phpunit.xml` deliberately does **not** pin a
   timezone, so a future tz-sensitive regression surfaces instead of being masked.

## Consequences

- **Positive:** Attendance math, slot booking, reminders, and `.ics` invites are correct
  under the shipped IST default (and any other timezone). Fresh-checkout test runs are green.
- **Trade-offs:** Normalization is per-ingress-point, not global — the 115 models extend
  `Model` directly with no shared base, and overriding the cast everywhere was judged too
  invasive. A new endpoint accepting datetimes must remember `AppTime::parse()`; the helper's
  docblock states the rule.
- **Deferred:** a shared base-model `fromDateTime` override making the cast itself
  zone-safe; storing all datetimes as UTC with per-tenant display timezones (whitelabel).

## Verification

`php artisan test` — **795 passing** under `APP_TIMEZONE=Asia/Kolkata` **and** under
`APP_TIMEZONE=UTC` (previously 790 + 5 failing under IST). Pint clean.
