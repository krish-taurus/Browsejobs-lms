# ADR 0024 — Support-desk AI: policy corpus + first-response deflection (P3.5d-a)

- **Status:** Accepted
- **Date:** 2026-07-17
- **PRD:** §6.13 (Student Support Desk — AI first-response / deflection layer), §7 (human approval), §6.8 (fee/EMI/escalation ladder — the seeded policy source)
- **Supersedes / relates to:** ADR 0012 (support desk — the non-AI spine this rides on), 0017 (AI tutor RAG — retrieval + citations reused), 0014/0016 (AI gateway), 0022 (content AI — corpus ingestion)

## Context

P2.7 shipped the full support desk and explicitly deferred its AI layer to P3.5
(`AiPurpose::SupportDeflection` and `SupportReply` have been declared with zero call
sites since). PRD §6.13 asks for four AI capabilities: a deflection layer, triage
(category + urgency/sentiment + duplicate detection), AI-drafted staff replies, and a
weekly themes report. The build playbook's one-liner omits duplicate detection; the PRD
governs.

That is too much for one slice against a feature that already has a complete non-AI
spine, so P3.5d is split. **This ADR covers P3.5d-a only: the support corpus and the
deflection layer.** P3.5d-b (triage, reply drafts, themes) is scoped in
`docs/session-prompts.md` and is not built here.

## Decision

1. **Deflection is authenticated-students-only.** The student desk is already
   `auth:sanctum` at `me/tickets` and §6.13 places it inside the student dashboard, so
   there is no anonymous-lead deflection path — and therefore no hole in the per-user
   token budget, which the gateway can only enforce against a `User`. `tickets.lead_id`
   remains CRM correlation and is not a deflection entry point. `POST me/tickets/deflect`
   is `throttle:ai` per CLAUDE.md.

2. **The support corpus is `knowledge_documents`, not a new table.** `source_id` was
   already nullable, so a policy article needs no synthetic source row, and
   `source_type='support'` slots into the existing
   `(tenant_id, source_type, source_id)` index. One additive migration adds a nullable
   `category` (a `TicketCategory` value) so a payments question retrieves fee policy
   rather than course material; it is null for every tutor document.
   `KnowledgeRetriever::retrieve()` gains `?array $sourceTypes` and `?string $category`,
   both defaulting to null — the tutor's whole-corpus behaviour is byte-for-byte
   unchanged. The filters apply in SQL ahead of the 200-row candidate cap, so a scoped
   search cannot be crowded out by unrelated documents.

3. **Deflection is assistive and never a gate. Every failure returns `null`.** Feature
   off, nothing retrieved, budget exhausted, transport down, empty answer, or confidence
   below the floor all mean "no answer — let the ticket through", always HTTP 200. This
   **deviates from the slice's own scope**, which specified a 429 on budget-exceeded
   mirroring `TutorController::guardBudget`. That mirror is wrong here: for the tutor the
   answer *is* the product, so a 429 is honest; for deflection the answer is a bonus on
   the way to a human, and erroring a student out of reaching support to tell them about
   a token budget inverts the feature's purpose. A support desk that swallows tickets
   when the model is down is worse than one with no AI at all.

4. **Citations are derived, never parsed.** `CitationResolver` gains a public
   `resolve(array $chunkIds)` and is reused as-is: a citation names a chunk that was
   actually retrieved, so the model cannot hallucinate a source. Support documents have
   no course/lesson, so they render as plain labels rather than links.

5. **Confidence is a shared fail-safe.** `TutorConfidence::split()` now holds the single
   `CONFIDENCE:` parse (missing/unparseable → `Low`), used by both `AskTutor` and
   `DeflectTicket`; `rank()`/`meets()` back the configurable
   `support.ai.min_confidence` floor (default `medium`, so a `low` answer is never
   shown). The enum is reused rather than duplicated — its name is now inaccurate, and
   renaming it to `AiConfidence` is deferred rather than churn this slice.

6. **`ticket_deflections` makes the claim measurable.** The PRD asserts deflection
   "typically deflects 30–50% of volume". A row is written when an answer is offered and
   resolved to `accepted` (no ticket) or `proceeded` (`ticket_id` backfilled to the
   ticket it failed to prevent), idempotently. Without this the feature would look built
   and could deflect nothing, indistinguishably. A low accept rate means the corpus is
   thin — not that the model is bad.

7. **Student data comes from `TicketStudentContext`, extended once.** The staff sidebar
   payload already carried batch/fee/attendance; it gains `fee.next_due`
   (amount + date of the next unpaid instalment), which is what a student asking "when
   is my EMI due?" actually wants and the PRD's own example answer requires. One payload
   serves both surfaces. Amounts are formatted from paise with integer math only.

8. **Synchronous, billed to the student.** Interactive like `AskTutor` — the documented
   exception to CLAUDE.md's queue-every-AI-call rule — and billed to the student's own
   daily budget, since it answers their own question. Prompt is
   `resources/prompts/support_deflection.v1.md`; it forbids inventing policy, quoting
   statistics, promising jobs/placement/salary, and granting waivers or extensions
   (a human's call, never the assistant's). This action never creates or modifies a
   ticket: `CreateTicket` remains the single path a ticket is born through.

## Consequences

- **Positive:** The desk answers fee/EMI/refund questions instantly from written policy
  with honest citations, and cannot block anyone from reaching a human. The tutor is
  untouched. Deflection is complementary rather than overlapping: `tutor.v2.md` is
  explicitly told to refuse fees/refunds/deadlines/account questions and send them to a
  human — this is the layer that answers them.
- **Trade-offs:** Retrieval is lexical (no embeddings — `knowledge_chunks.embedding`
  remains the null placeholder from ADR 0017), so a question phrased far from the policy
  wording will simply not deflect. Preferred over a wrong confident answer on a fee
  dispute. Staff have no UI to author `support` documents yet — the corpus is seeded, so
  editing means a seeder change or tinker until P3.5d-b or an admin CRUD lands.
- **`report()` on a swallowed AI failure** is a small, deliberate deviation from the
  repo's habit of silent degradation. Deflection failing silently on an interactive path
  is exactly the failure you would never notice.
- **The seeded corpus is starter content, not a finished one.** Every fact is transcribed
  from the PRD; the operational questions that drive most real ticket volume
  (batch-transfer rules, refund mechanics past the 30-day window, hardware requirements)
  are absent because the PRD does not answer them. **Deflection quality is a function of
  this corpus, not of the model** — tracked as a founder input in
  `docs/session-prompts.md`.
- **Deferred (owner-visible, not gaps):** P3.5d-b — triage/urgency/sentiment
  queue-jumping, duplicate detection, AI reply drafts, weekly themes report; an admin UI
  for authoring support documents; embeddings; the `TutorConfidence` → `AiConfidence`
  rename; a gateway that returns its `AiEvent` (today every consumer re-queries the last
  event by user+purpose, mirroring `ReportNarrator`).

## Verification

`php artisan test` — full suite **452 passing** (up from 435), incl.
`Feature/Support/TicketDeflectionTest` (17 tests): a grounded answer with derived
citations and the `CONFIDENCE` line stripped, billed to the student with no ticket
created; the student's own next instalment passed into the prompt verbatim; a payments
question **not** answered out of course material; cross-category retrieval refused;
low-confidence, missing-confidence, feature-off, model-down and budget-exhausted all
degrade to no-answer **with the ticket still raiseable**; accept/proceed outcome
recording, idempotent re-resolution, unknown `deflection_id` still raises the ticket;
auth, validation, and **cross-tenant denial** on both the deflection row and the corpus
(tenant A never retrieves tenant B's policy). Pint clean. Fresh `migrate --seed` seeds 8
chunked `support` documents across payments/technical/training/other plus one demo
deflection, verified by inspection. Frontend: the student support page asks the
deflection layer on submit, renders the answer + citations with "Yes, that answers it"
and a never-buried "No — raise the ticket anyway", and raises the ticket directly when
there is no answer — `npm run typecheck`, `lint`, and `build` all pass.

**Known gap (not done):** the Playwright spec asserts the route guard only, not the
deflect → accept / deflect → raise-anyway flows. Student sign-in is OTP-based and not
automatable in the current harness — the same limitation `portal-reports.spec.ts`
documents — so those flows are covered by Pest against the real endpoints instead. A
browser-level student flow needs an OTP test bypass, which is its own decision and is
not taken here.
