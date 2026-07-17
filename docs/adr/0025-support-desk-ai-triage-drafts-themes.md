# ADR 0025 — Support-desk AI: triage, reply drafts, themes report (P3.5d-b)

- **Status:** Accepted
- **Date:** 2026-07-17
- **PRD:** §6.13 (AI triage — category suggestion, urgency/sentiment queue-jumping, duplicate detection, AI-drafted reply suggestions, weekly themes report to admin), §7 (human approval)
- **Supersedes / relates to:** ADR 0024 (P3.5d-a — the support corpus and deflection this builds on), 0012 (support desk), 0021 (reports/digests — the themes report mirrors this machinery)

## Context

P3.5d-a shipped the corpus and the deflection layer. This closes P3.5d with the
staff-facing half: triage, reply drafts, and the weekly themes report. It also lands the
`JsonOutput` decision recorded in `docs/session-prompts.md`.

Two things about the existing system shaped this, and both contradict the slice's own
scope note — the scope was written before reading the P2.7 internals closely:

1. **Priority does not drive SLA.** Due-times come from `ticket_routes` per *category*.
   The scope claimed a sentiment-driven raise "moves SLA due-times". It does not. A jump
   changes who is seen first, not what was promised.
2. **Priority drove nothing at all.** The staff queue ordered by `id DESC`, so
   `priority` was a stored, displayed, and entirely inert field. "An angry payment
   dispute jumps the queue" would have been decorative: the raise recorded, the audit
   written, and the ticket sitting exactly where it was.

## Decision

1. **`JsonOutput` is built, and only for the fragile part.** `app/Services/AI/JsonOutput`
   owns brace-substring extraction + `json_decode` + shape check, with unit tests. Triage
   reads four fields from one call and was going to be the sixth private copy of that
   dance. It is deliberately **not** a schema validator — the gateway stays text-in/
   text-out and domain validation stays with each consumer. **The five existing parsers
   are not migrated**: that refactor risks five working, individually-tested consumers
   for a cosmetic win, and is out of scope.

2. **AI signals sit beside human ones in `ai_*` columns, never on top.** Category and
   routing stay rule-owned — the PRD asks for a category *suggestion*, and re-routing on
   a model's say-so moves work between teams with nobody accountable. `ai_category` is
   surfaced only when it disagrees with the human's choice.

3. **`priority` is the one field triage may write, and it is fenced.** Raise only, never
   lower; only on first triage (`ai_triaged_at`) of a ticket no staff member has touched;
   `ai_priority_raised` records that a model did it; audited as
   `ticket.priority_raised_by_ai`; and reversible in one click via `ClearAiPriority`
   (audited as `ticket.ai_priority_cleared`), which refuses to touch a priority a human
   set. An `angry` sentiment floors at `high`, which is what makes the PRD's sentence
   literally true.

4. **The staff queue now orders by priority, then recency.** Without this the whole
   queue-jumping feature is theatre. A raised ticket also carries "AI raised to high" in
   the list, so the reordering explains itself rather than quietly outranking a human's
   sense of the queue.

5. **Triage signals are staff-only, gated on the viewer.** `TicketResource` serves the
   student's own "My Tickets" as well as the workspace. Showing a student our sentiment
   read of them ("we scored you angry") would be a betrayal and useless to them — the
   same reasoning the PRD uses to keep AI-likelihood flags to trainer eyes only. The gate
   is on `user_type`, not the route, so a future surface cannot leak them by forgetting.

6. **Reply drafts have no approval flow, deliberately.** PRD §7 gates AI output that
   reaches a student unreviewed. A draft reaches nobody: it lands in the staff member's
   reply box, they edit it, and `PostTicketReply` sends it under their name. The staff
   member *is* the human in the loop; a second approver would be approving a draft that a
   human must approve again by pressing Send. Grounded in the same `support` corpus as
   deflection, billed to the staff member who asked.

7. **Both prompts refuse to give anything away.** `support_reply.v1.md` and
   `support_triage.v1.md` forbid promising a waiver, refund, discount, exception, or
   deadline extension, accepting fault, quoting statistics, or promising a hiring
   outcome. Triage is told to judge the ticket only on what it says — never to infer from
   a name, and never to read a language or spelling difference as urgency or anger.

8. **The themes report counts, then narrates.** `BuildSupportThemes` computes volume by
   category, distress, duplicates, breaches, CSAT, and the P3.5d-a deflection accept rate
   in PHP; the model only interprets. Arithmetic an LLM re-derives is arithmetic that can
   be wrong, and this report exists to drive decisions. In-app only — the counselor digest
   pages people because a student at risk is time-critical; paging an admin about last
   week's ticket mix trains them to ignore the channel that carries urgent things.
   `accept_pct` is null rather than 0% when nothing was offered: "we never tried" and "we
   tried and it never landed" are different problems.

9. **Billed to staff, never the student.** Triage bills the assignee (else an admin) and
   *skips entirely* if there is nobody to bill, rather than charging a student for our own
   bookkeeping. Follows `GradeSubmission::billingUser()`.

## Consequences

- **Positive:** Staff open the queue and the upset, blocked, and repeated tickets are
  already at the top with a one-line reason and an undo. The desk degrades to exactly the
  P2.7 workspace whenever the model is off, down, over budget, or talking nonsense.
- **Trade-offs:** Sentiment is advisory and will be wrong sometimes; the design assumes
  that and under-reacts (an unreadable label is `neutral`, not `angry`). Duplicate
  detection only considers the same student's five most recent open tickets — a
  cross-student duplicate (fifty people reporting one outage) is not detected, and the
  themes report is the surface where that pattern shows up instead.
- **`ai_category` is a suggestion nobody is forced to act on.** If staff ignore it, the
  category stays wrong and routing stays wrong. Auto-routing on it is a decision for
  later, once there is evidence the suggestion is trustworthy.
- **Deferred:** migrating the five legacy parsers to `JsonOutput`; auto-routing on the
  category suggestion; cross-student duplicate clustering; an admin UI for authoring
  `support` documents (still seeder-only, carried from ADR 0024); embeddings; the
  `TutorConfidence` → `AiConfidence` rename.

## Verification

`php artisan test` — full suite **498 passing** (up from 452), incl.:
- `Feature/Support/TicketTriageTest` (13): four signals stored and billed to staff not
  the student; the suggestion never overwrites the human's category; an angry ticket
  raised to high **with an audit entry**; never lowers; never re-prioritises a ticket a
  human is working; idempotent on retry (and never pays for a second call); a duplicate
  linked only when it is an id the model was shown; a cross-tenant/hallucinated id
  ignored; nonsense, unknown labels, model-down, and switched-off all leave an ordinary
  untriaged ticket; skips rather than billing a student when no staff exist; duplicate
  candidates never include another student's tickets.
- `Feature/Support/TicketAiVisibilityTest` (8): staff see the signals; **a student never
  does, on show or index**; the queue orders by priority so a raise actually moves a
  ticket; undo works and audits; undo refuses a human-set priority; validation;
  cross-tenant 404.
- `Feature/Support/TicketReplyDraftTest` (8): drafts without posting a message or
  starting the SLA clock; billed to the asking staff member; grounded in corpus + account;
  told plainly when no policy covers it; model-down and switched-off return no draft;
  permission and cross-tenant denial.
- `Feature/Support/SupportThemesTest` (9): aggregation, the deflection accept rate,
  never-offered vs never-accepted, silence on a quiet week, week windowing, cross-tenant
  isolation, idempotence, **counts still delivered when the model is down**, and the
  scheduled command.
- `Feature/AI/JsonOutputTest` (8): fenced/prose-wrapped extraction, malformed, list, and
  inverted-brace inputs, and the positive-int coercions.

Pint clean. `npm run typecheck`, `lint`, `build` pass. Fresh `migrate --seed` triages the
seeded demo ticket and adds an angry payments ticket that demonstrates the raise, the
queue jump, and the undo.

**Known gap (not done):** `e2e/admin-support-triage.spec.ts` is written but **was not
successfully executed**. The local environment had a second Next.js process (another
Claude Code session) holding port 3000 and sharing the same `.next` directory; the two
clobbered each other's artifacts, and the **pre-existing** `admin-support-desk.spec.ts`
fails identically in that state — so this is environmental, not a defect in either spec.
Running it needs a clean run with a single Next process on :3000 (Sanctum's stateful
domains and CORS are pinned to that port, so a different port fails login). Until someone
runs it green, treat the staff triage UI as covered by Pest at the API layer only.
