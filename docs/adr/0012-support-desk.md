# ADR 0012 — P2.7 Student Support Desk: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.13 + BUILD-PLAYBOOK P2.7

## Context

A Help & Support area where every student concern reaches the right team fast:
pick a category (Payments · Technical · Mentorship · Training · Interview Prep ·
Other) → ticket issued instantly → auto-routed to a team + assignee → per-category
SLA (first-response + resolution, 75% warning, auto-escalate to Admin on breach) →
staff workspace (thread, internal notes, canned responses, student-context sidebar)
→ student My Tickets with two-way WhatsApp threading, 7-day reopen, and CSAT on
resolve. Every ticket event lands on the CRM timeline + audit log.

The desk maps almost 1:1 onto existing CRM primitives — this milestone is mostly
cloning proven patterns (`AssignLead` round-robin, `CheckLeadSla` delayed job,
`RecordInboundMessage` WhatsApp seam, `CrmAssignmentRuleController` settings) onto
the pre-existing `SupportTeam`/`StaffProfile`/`handle-tickets` substrate (P1.8).

**Founder-confirmed scope (this session):**
- **AI layer deferred to P3.5** — the non-AI ticketing spine ships now; pre-ticket
  deflection, triage/sentiment queue-jumping, reply drafts, and weekly-themes land
  in P3.5 with the AI gateway.
- **Full two-way WhatsApp** — outbound ack/ping + inbound replies thread onto the
  student's open ticket.
- **Training routing = batch trainer + team fallback** — a new `batches.trainer_id`.

## Decisions

1. **`TicketRoute` (per-category config) + `SupportTeam` (member pool).** A seeded
   `ticket_routes` row per category holds the admin-editable routing strategy
   (`team` / `batch_trainer` / `admin`), `support_team_id`, SLA minutes, and a
   `round_robin_pointer`. `SupportTeam.members` is the assignment pool. Fixed
   product categories are an enum (`TicketCategory`); per-tenant config is rows.
   (PRD names `ticket_teams`; `ticket_routes` is that map.)
2. **CSAT + escalation are columns on `tickets`, not separate tables.** PRD lists
   `csat_ratings`/`escalations`, but a 1:1-with-ticket column set
   (`csat_rating/comment/at`, `escalated_at/escalated_to_id`) is leaner; the
   escalation *event* is captured by an audit row (`ticket.escalated`, CLAUDE.md
   required) + a timeline entry.
3. **SLA armed at create, swept hourly.** `CreateTicket` dispatches three delayed
   `CheckTicketSla` jobs — `warning` at 75% of first-response, `first_response` and
   `resolution` at their due-times — each idempotent and self-guarding against the
   sync driver (mirrors `CheckLeadSla`). A `support:check-sla` command re-scans
   active tickets whose due passed unflagged (downtime safety net). Breach →
   `EscalateTicket` (reassign to the first Admin, audit + notify).
4. **Full two-way WhatsApp.** Outbound via `Messenger` (a `NotifyTicket` helper
   with the right deep link). Inbound: `RecordInboundMessage` gained a branch that
   correlates the sender phone to a student `User` with an open ticket and appends
   the body as a `whatsapp` `TicketMessage` (+ assignee notify). The existing
   lead-correlation path is unchanged.
5. **`batches.trainer_id` is a plain nullable indexed column, no DB FK** — added via
   an ALTER (SQLite can't add an FK on ALTER; same choice as P2.6's
   `voucher_issue_id`). `Batch::trainer()` added. Training routing: the student's
   active batch `trainer_id` → else the Trainer team round-robin.
6. **Student `me/*` actions wrap in `TenantContext::run($student->tenant, …)`** (no
   `tenant.user` on that group); the `me/tickets/{ticket}` routes fetch by
   `student_id` (the ownership + tenant boundary) rather than tenant route-binding,
   which would resolve unscoped.
7. **Notifications ride a queued listener.** `CreateTicket` dispatches
   `TicketCreated` (named in CLAUDE.md); the auto-discovered `NotifyTicketAssignee`
   listener acks the student + pings the assignee. Never inline in the request cycle.
8. **`handle-tickets` gate reused; no new permission** (RolePermission count
   unchanged). `support-agent` role + the 5 `SupportTeam`s are seeded by
   `SupportSeeder`, which also seeds the routes, canned responses, and a demo ticket.

## Consequences

- End-to-end (verified live on a seeded scratch DB): create → routed + assigned →
  student ack + assignee ping → staff reply sets first-response + In Progress →
  inbound WhatsApp threads onto the ticket → first-response breach escalates to
  Admin with an audit → resolve + CSAT.
- New scheduled command `support:check-sla` (`hourly`); production needs the cron
  to run it alongside `fees:run-ladder` + `conversions:run-nudges`.
- **Deferred (owner-visible):** the AI layer (pre-ticket deflection, triage +
  sentiment queue-jumping, reply drafts, weekly "top themes" report) — **P3.5**, it
  needs the AI gateway; the who's-who staff directory; a per-batch trainer
  assignment UI beyond the `trainer_id` field. Next: **P2.8 — Entitlement engine +
  self-paced tier**.
