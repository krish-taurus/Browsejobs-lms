# ADR 0009 — P2.4 Messaging hub: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.9 (Messaging & Automation Hub), §14.6, BUILD-PLAYBOOK P2.4

## Context

Phases P2.1–P2.3 left every notification as a log-only stub behind an interface,
each comment-marked for P2.4. This slice builds the single `Messenger` service
all of them route through — real WhatsApp + email + in-app channels (mocked in
tests), a template manager + banned-phrase linter, quiet-hours / frequency-cap /
opt-out guards, delivery logging, and a two-way WhatsApp inbound webhook.
Founder-confirmed scope: WhatsApp + email + in-app (web push deferred),
reply-detection seam (P2.5 owns the ladders), DB template manager + linter.

## Decisions

1. **One `Messenger` facade; channel adapters behind interfaces** — mirrors the
   Zoom/Razorpay client pattern. `WhatsAppClient` (+ `HttpWhatsAppClient` /
   `FakeWhatsAppClient`), email via a `MessageMail` Mailable + Laravel `Mail`,
   in-app via an `in_app_notifications` row, `PushSender` + `NullPushSender` (web
   push deferred). External sends are **queued idempotent jobs**
   (`SendWhatsAppMessage`/`SendEmailMessage`) that only fire a message still in
   the `queued` state.
2. **Every notifier routes through the Messenger.** The three `Log*` stubs are
   rebound to `Messenger*` implementations (`MessengerOtpNotifier`,
   `MessengerSessionNotifier`, `MessengerFeeNotifier`); each maps its call to a
   template key + vars. `Log*` impls stay for tests that force logging.
3. **Utility bypasses the guards; marketing respects them.** Transactional/
   utility messages (OTP, payment links, reminders, access notices) skip opt-out
   / quiet-hours / frequency-cap — DPDP consent came at lead/enrol. Marketing
   needs `marketing_opt_in`, honours **quiet hours (9pm–9am IST)** and the
   per-24h **frequency cap**. Guarded sends are **suppressed with a logged
   reason** (`not_opted_in` / `quiet_hours` / `frequency_cap`) rather than
   silently dropped or delay-queued — deterministic and re-attempted on the next
   scheduled run.
4. **Banned-phrase linter** (`config('messaging.banned_phrases')` — the §14.3
   never-claims) runs at template save (admin, hard 422) and at render (the
   message is logged `blocked`, never sent). Protects the WABA + radical-honesty
   compliance.
5. **One-tap deep links** — a `{{link}}` variable is filled from
   `MagicLinkService` (existing; `pay.`/`auth.` actions get the 15-min TTL).
6. **WhatsApp inbound reuses the Meta webhook pattern** (`whatsapp.signed`
   middleware = same `X-Hub-Signature-256`; GET `hub.challenge`). Inbound
   messages → a `message_in` timeline event + `leads.last_replied_at` +
   `LeadReplied` (the P2.5 auto-pause seam, ADR 0006's deferral); `statuses` →
   delivery/read updates on the `messages` log. Single-WABA tenant resolution
   via `services.whatsapp.tenant_slug`.
7. **The lead-welcome listener uses Laravel 11's automatic `app/Listeners`
   discovery** — no explicit `Event::listen`. (An explicit registration
   *double-fires* alongside discovery; caught in testing.) `SendLeadWelcomeMessage`
   is the repo's first queued listener, the event-driven path CLAUDE.md prefers.
8. **The in-app table is `in_app_notifications`, not `notifications`** — avoids
   colliding with Laravel's `Notifiable` `notifications` table (different schema).

## Consequences

- Every existing notification (OTP, class reminders, dunning, payment links, lead
  confirmation) now delivers through one audited, template-driven, guard-checked
  pipeline with a delivery log — and inbound replies land on the CRM timeline.
- New `manage-messaging` permission gates the admin log + template manager; the
  seeded permission count moved 13 → 14 (test updated).
- SQLite (test DB) can't add FK via `ALTER`, but the new tables are fresh
  creates; `leads.last_replied_at` is an indexed column only.
- **Deferred (owner-visible):** web push (VAPID + service worker + subscriptions);
  the CRM nudge-ladder sequence engine (**P2.5**, on the `LeadReplied` seam);
  AI-personalised bodies within approved frames (**P3**); real WhatsApp/SMTP
  credentials + Meta-approved template registration (founder inputs — tests +
  demo stay mocked).
