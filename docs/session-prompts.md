# Session Prompts — paste-ready, one per Claude Code session

Run each prompt in a fresh Claude Code session **rooted in this repo**
(`cd C:\Users\DESKTOP\browsejobs-lms` → `claude`). Use **plan mode** (Shift+Tab)
for anything non-trivial. After each session: skim the diff, confirm the green
checks, commit, `/clear`.

---

## Status (updated 2026-07-17)

**Phases 1–2 — COMPLETE.** P1.1–P1.8 (scaffold, tenancy, auth, curriculum/
batches, Zoom, reminders, public site, portals). P2.1 CRM · P2.2 payments ·
P2.3 fee ladder · P2.4 messaging · P2.5 conversion · P2.6 review/voucher ·
P2.7 support desk · P2.8 entitlement engine.

**Phase 3 — in progress.** P3.1 AI gateway/telemetry/labs · P3.2 coach panel ·
P3.3 AI tutor (RAG) · P3.4 MCQ automation · P3.4b assignment grading · P3.4c
certificates · P3.5a reports/digests · P3.5b content AI · P3.5c syllabus
generator — all merged to `main` (`767be47`).
**Next: P3.5d — support-desk AI** (the last P3.5 slice), then P3.6 leaderboards.

**Environment facts for a fresh session:**
- API tests: SQLite in-memory (`php artisan test` in `apps/api`) — 435 passing
  as of P3.5c.
  Pint: `./vendor/bin/pint`. Web: `npm run typecheck && npm run lint && npm run
  build` in `apps/web`. E2E: `npx playwright test` (chromium installed; needs
  both dev servers; visit the app on **localhost**, not 127.0.0.1 — cookie
  same-site).
- Docker works: `docker compose -f docker/docker-compose.yml up -d`
  (MySQL/Redis/Mailpit/MinIO). Local `.env` uses MySQL.
- Composer works from **PowerShell** (`composer …`), not Git Bash.
- Local admin: `test@example.com` / `password` (staff, 2FA off). Dev servers:
  `php artisan serve --port=8000` + `npm run dev` (:3000).
- Read `CLAUDE.md` + `docs/browsejobs-lms-requirements.md` (incl. §14 addendum)
  before building. ADRs 0001–0023 in `docs/adr/`.

---

## Scoping note — P3.5d is split into two sessions

The playbook's one-liner ("Support-desk AI: deflection answers from student
data, triage + sentiment queue-jumping, reply drafts, weekly themes report")
under-counts PRD §6.13, which also requires **duplicate detection**. That is
four AI capabilities over a feature that already has a full non-AI spine, so it
is split:

- **P3.5d-a — support corpus + deflection.** The conversion-relevant half. Gated
  on content authoring (the KB has no fee/refund/policy documents today), so it
  needs founder input — see the outstanding-inputs list below.
- **P3.5d-b — triage + reply drafts + themes report.** Pure staff-side; no new
  corpus needed.

Two decisions taken at scoping time, to be recorded as **ADR 0024** when P3.5d-a
lands:

1. **Deflection is authenticated-students-only.** The student ticket surface is
   already `auth:sanctum` at `me/tickets` and PRD §6.13 puts the desk inside the
   student dashboard, so there is no anonymous-lead deflection path and no
   token-budget hole. `tickets.lead_id` stays CRM correlation only.
2. **Build one small structured-output helper** (`app/Services/AI/JsonOutput`)
   for triage, which emits four fields in one call. The existing five consumers
   each hand-roll a brace-substring + `json_decode` parser; a sixth copy is the
   wrong place to preserve consistency. **Do not refactor the existing five** —
   new code only, out of scope for this slice.

---

## NEXT → P3.5d-a — Support corpus + AI deflection

Read `CLAUDE.md` and `docs/browsejobs-lms-requirements.md` fully (PRD §6.13 —
the AI first-response/deflection layer — and §7 for the human-approval flow).
This is the first half of **P3.5 — support-desk AI** from
`docs/BUILD-PLAYBOOK.md`. Read ADR 0012 (support desk), 0017 (tutor RAG) and
0022 (content AI) first.

**Environment:** backend tests on SQLite in-memory with `sync`/fake queues; the
AI transport is faked via `FakeAiClient` — never call a real model in tests. The
student portal pattern to follow is `apps/web/src/app/(portal)/support/*`.

**Already exists — build on it, don't recreate:**
- The whole non-AI desk (P2.7): `tickets`/`ticket_messages`/`ticket_routes`,
  `Actions/Support/{CreateTicket,RouteTicket,PostTicketReply,…}`, the SLA
  engine, and `Actions/Support/TicketStudentContext` — **the sidebar payload
  (batch, fee status, attendance, risk, recent activity) is exactly the
  "student's own data" deflection needs. Reuse it; do not build a second one.**
- `Support/Tutor/KnowledgeRetriever` — generic, knows nothing about tutors:
  `retrieve(array $terms, int $k, ?int $lessonId = null)`, lexical only
  (`knowledge_chunks.embedding` is a deliberate null placeholder — no vectors).
- `Support/Tutor/CitationResolver` — citations are derived from retrieved
  chunks, not parsed out of the model's answer. Follow that; it is the pattern
  that keeps citations honest.
- `AiPurpose::SupportDeflection` — **already declared, zero call sites.** This
  slice is its first consumer. `AiGateway::complete(User, AiPurpose, promptName,
  version, vars, opts)` enforces the per-student daily budget and logs
  `ai_events`.

**Build:**
1. **Support corpus.** `knowledge_documents.source_type` is a plain string with
   an index on `(tenant_id, source_type, source_id)` — so a new `support` value
   needs **no migration**. Add a `sourceTypes` filter param to
   `KnowledgeRetriever::retrieve()` (default null = today's behaviour, so the
   tutor is unaffected) and index tenant support content: fee/EMI policy, refund
   window, the 30-day money-back terms, rescheduling, access rules. Author these
   as seeded `support` documents. **Every figure comes from the PRD fee model —
   invent nothing**, and the compliance rules bind here as hard as anywhere:
   never render "guaranteed job"/"assured placement", and any stat carries the
   mandatory `DISCLAIMER`.
2. **`Actions/Support/DeflectTicket`** — takes the student's draft category +
   description, retrieves from the `support` corpus scoped to that category,
   composes with `TicketStudentContext`, calls the gateway with a new versioned
   prompt `resources/prompts/support_deflection.v1.md`, billed to the
   **student** (their own budget, like the tutor). Returns an answer + derived
   citations + a confidence signal. **Synchronous, not queued** — this is
   interactive like `AskTutor`, which is the documented exception to the
   queue-every-AI-call rule. Fail-safe: on `AiBudgetExceeded`, transport error,
   low confidence, or empty retrieval → **return no deflection and let the
   ticket through**. A support desk that swallows tickets when the model is down
   is worse than no deflection.
3. **Endpoint.** `POST me/tickets/deflect` alongside the existing student
   routes, `throttle`d per CLAUDE.md's rate-limit-AI-endpoints rule; 429 with
   the `{error:{code:'ai_budget_exceeded'}}` envelope on budget exceed (mirror
   `TutorController::guardBudget`).
4. **Student UI.** In `(portal)/support`, on submit: show the AI answer with
   citations *before* the ticket is created — "Does this answer it?" → accept
   (no ticket) or **Raise the ticket anyway** (always available, never buried;
   proceeding must never cost an extra step). Loading/empty/error states.
   Deflection is assistive, never a wall.
5. **Measurement.** Record offered/accepted/proceeded so the PRD's 30–50%
   deflection target is observable rather than asserted — a `ticket_deflections`
   table (`BelongsToTenant`, student_id, category, question, answer,
   `ai_event_id`, outcome enum, cited_chunk_ids). This is also the honest way to
   find out whether the corpus is good enough.

**Conventions:** PHP 8.3 strict, thin controllers → Form Requests → Actions,
`BelongsToTenant` + FK/index on `tenant_id` for every new model, prompts as
versioned files (never inline strings), design tokens only, one blue primary CTA
per view.

**Definition of Done:** migration + model + Actions + endpoint + student UI;
Pest — deflection happy path, budget-exceeded → 429 **and ticket still
raiseable**, transport failure → ticket still raiseable, retrieval scoped to the
`support` corpus (an academic question does not deflect a payments ticket),
tutor retrieval unchanged by the new filter, and **cross-tenant denial** on the
new model and the corpus; seed a support corpus + a demo deflection so
`/support` is demo-able; Playwright covering deflect→accept and
deflect→raise-anyway (there is **no** student-side support spec today — this
adds the first); `php artisan test`, Pint, `npm run typecheck`, `npm run build`
green. Write **ADR 0024** recording the two decisions above.

Commit as `feat(P3.5d-a): support corpus + AI deflection` when green.

---

## After P3.5d-a

**P3.5d-b — triage + reply drafts + themes report** (PRD §6.13). Draft the
prompt from this scope when P3.5d-a lands:
- **`TriageTicket`** — queued off the existing `TicketCreated` event, emitting
  category suggestion + urgency + sentiment + duplicate-of in one call via the
  new `JsonOutput` helper. New `AiPurpose::SupportTriage` case (does not exist
  yet). Bill **staff**, not the student — follow `GradeSubmission::billingUser()`,
  which bills the trainer to keep the student's tutor budget intact.
- **Sentiment queue-jumping writes to `tickets.priority`, a field staff own by
  hand, and it moves SLA due-times.** So: write AI signals to new nullable
  `ai_*` columns, never silently overwrite a human's `priority`; audit-log the
  jump (PRD already mandates audit for escalations); surface an "AI raised this"
  affordance in the queue. Staff must be able to see and undo it, or they will
  stop trusting the queue.
- **`DraftTicketReply`** — staff-facing, so **no approval gate is needed: the
  staff member is the human in the loop**. It drafts into the reply box, never
  sends. Hangs off the existing canned-response `<select>` slot in
  `admin/(panel)/support/[id]`. Reuses the declared-but-unused
  `AiPurpose::SupportReply`.
- **Weekly themes report to admin** — mirror the P3.5a digest machinery and
  `resources/prompts/counselor_digest.v1.md`. New `AiPurpose` case needed.
- Duplicate detection is a PRD §6.13 requirement the playbook line omits — it
  rides in the triage call rather than being its own pass.

---

## Outstanding founder inputs (blocked on Krish — not on code)

1. **Reviews CSV** — real Google/WhatsApp review exports
   (`author_name,rating,body,source,reviewed_on,course_slug`), then:
   `php artisan reviews:import <file>`. The /reviews wall renders 1,000+
   smoothly; reviews are never fabricated.
2. **Google sign-in keys** — set `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`
   in `apps/api/.env` (redirect URI `{APP_URL}/auth/google/callback`); the
   button then appears on /student and /register automatically.
3. **Python Backend brochure** — the /courses/python-backend page defers to
   counselling until the syllabus arrives.
4. **Legal placeholders** — [CIN], [GST], Grievance Officer name +
   retention/refund windows in /privacy-policy, /terms, /refund-policy.
5. **Zoom + Razorpay + WhatsApp credentials** — needed before P2.2+ can be
   exercised against real sandboxes (tests stay mocked regardless).
6. **Support corpus source-of-truth (blocks P3.5d-a quality).** Deflection can
   only answer from documents that exist; the KB today indexes course content
   only. The PRD fee model covers registration/EMI/placement-fee/money-back, but
   the real 30–50% of ticket volume is operational: reschedule rules, recording
   access windows, batch-transfer policy, refund mechanics, what happens on a
   missed EMI. Either confirm the PRD text is the whole truth, or supply the
   actual policy answers. **The deflection rate is a function of this list, not
   of the model** — shipping the code against a thin corpus produces a feature
   that looks built and deflects nothing. Overlaps with input 4 (retention/
   refund windows in /privacy-policy, /terms, /refund-policy).
