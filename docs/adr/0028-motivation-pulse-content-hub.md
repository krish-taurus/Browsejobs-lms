# ADR 0028 — P3.7 Motivation engine, Market Pulse & Content Hub

- **Status:** Accepted (completes Phase 3)
- **Date:** 2026-07-17
- **Context:** PRD §6.18 (offer celebrations + gap guidance) and §6.19
  (Market Pulse + Content Hub).

## Decisions

**Celebrations are consent-first by schema.** `celebrations.consented_at` is
NOT NULL and the admin create endpoint requires an accepted consent assertion —
an unconsented celebration cannot exist. Anonymous mode keeps `student_id`
linked (the guidance card needs their reference point) but only ever renders
`anonymous_label`. Publishing is idempotent (single broadcast) and fans out
in-app only; promotional WhatsApp stays behind the weekly opt-in digest.
The P4 placement module will *create* celebrations automatically at offer
acceptance; today staff record them manually — the machinery is identical.

**"Your Path to the Same" is lazy, cached, and fail-safe.** Cards generate on
demand per (celebration, recipient) — never N AI calls at broadcast time —
from the recipient's own `ScoreCalculator` mastery/needs_work, billed to the
recipient under the standard budget. On budget/transport failure it degrades
to deterministic actions built from the same weak modules (source: fallback),
so the button never dead-ends. Prompt (`gap_guidance.v1`) hard-forbids job
guarantees and salary promises. One-tap mock booking is stubbed until P4.

**Market Pulse never invents news.** The digest is built ONLY from
staff-curated `pulse_items` (title/source/URL, optional course tie-in); with
nothing curated in the window there is simply no digest. AI summarisation
(`AiPurpose::MarketPulse`, prompt requires per-claim source attribution) falls
back to a deterministic source-attributed list. One digest per tenant per day
(unique key), built at 06:45 by `digest:market-pulse` or on demand from the
admin page. Weekly WhatsApp/email (`pulse:weekly-send`, Mondays) uses the
MARKETING message category, so the P2.4 Messenger guard enforces explicit
opt-in, quiet hours, and frequency caps — verified by test.

**Content Hub is manual-first.** Releases are staff-entered today and notify
active students in-app (capped fan-out); YouTube RSS / Instagram Graph
ingestion is a config-gated adapter behind the same table once channel
credentials arrive (founder input). Opens track through `RecordActivity` as
the new `content_viewed` type, feeding the engagement score per PRD.

**Surfaces:** student `/pulse` page (digest + wall + feed) with a `Pulse` nav
item; admin `/admin/engagement` (all three sections) gated by
`can:manage-messaging`.

## Deferred

- Auto-celebration from placement offers (P4.5 trigger).
- Real mock booking in guidance cards (P4.1/P4.4).
- YouTube/Instagram auto-ingestion (needs channel credentials).
- Per-release web push (in-app + the P2.4 hub cover delivery today).
