# ADR 0017 — P3.3 AI Tutor (RAG): design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.4 + CLAUDE.md "AI Service Layer" + BUILD-PLAYBOOK P3.3 (first real consumer of the P3.1 AI gateway).

## Context

P3.1 shipped the AI gateway; P3.2 consumed telemetry with rule-based scoring (no
tokens). P3.3 is the **first feature that actually calls the model**: an AI Tutor that
answers student questions grounded in course content, with citations, a coding-lab
hint-ladder, and escalation to trainers — inside the per-student daily token budget.

**Two constraints from exploration shaped the design:**
1. The gateway is **Anthropic text-completion only** (no embeddings transport) and
   Pest runs on **SQLite in-memory** (MySQL FULLTEXT unusable). → retrieval is lexical.
2. **Lessons/topics have no body or transcript text yet** — only `courses.description`/
   `tagline`, `programs.description`, and `coding_labs.instructions` are retrievable.
   Transcripts are a later phase (P3.5). → the KB is existing content + trainer-authored.

**Founder-confirmed scope (this session):** lexical retrieval (not embeddings); KB from
existing content + a trainer authoring surface; escalation reuses the Support Desk.

## Decisions

1. **Lexical retrieval over a chunk store — no embeddings.** `knowledge_documents`
   (source: course/program/coding_lab/manual) → `knowledge_chunks` (~200-token windows
   on paragraph boundaries, `IngestKnowledgeDocument`). `KnowledgeRetriever` tokenizes
   the question (lowercase, drop stopwords + <3-char tokens), fetches candidates via
   portable `LIKE` (SQLite-safe), and ranks in PHP: `score = Σ termFreq / sqrt(term_count)`
   (length normalization) + a ×1.5 bonus when a chunk's document is the lab the student
   is on; deterministic tie-break by `(document_id, ordinal)`. The `embedding` column is
   **nullable and unused** — semantic search drops in later without a reschema.
2. **KB = existing content + trainer-authored.** `RebuildTutorIndex` (`tutor:reindex`,
   scheduled weekly) `updateOrCreate`s a document per course/program/lab keyed by
   `(source_type, source_id)`, carrying citation `course_id`/`lesson_id`, then chunks it;
   `source_type=manual` docs (authored via `v1/admin/knowledge`, chunked on save) are
   left untouched by reindex.
3. **Synchronous answer, not queued.** `AskTutor` runs in the request cycle — chat is
   interactive, exactly like the Judge0 coding labs (ADR 0014). The gateway still logs
   `ai_events` + enforces the budget; only the escalation `CreateTicket` fans out queued
   jobs. `throttle:ai` + a bounded `max_answer_tokens` cap cost and latency. `AiPurpose::Tutor`
   already existed; no new purpose.
4. **Confidence via a prompt-emitted trailing line.** `tutor.v2.md` (bumped from the
   scaffolded v1) ends with `CONFIDENCE: high|medium|low`; `AskTutor` extracts it with a
   strict regex, strips it from the displayed answer, and **defaults to Low when missing**
   (fail-safe → escalate). `TutorConfidence::fromLabel` centralizes the parse.
5. **Escalation reuses the Support Desk.** A new `TicketCategory::Academic` routed via
   `batch_trainer` (`config/support.php` → `academic` → training team, landing on the
   student's own batch trainer). `AskTutor` escalates on **Low confidence OR a repeat
   question** — the same normalized token-set `question_fingerprint` asked ≥ threshold
   times in a window — by calling `CreateTicket` and linking `ticket_id` on the turn.
   No separate trainer-thread table.
6. **Hint-ladder never leaks solutions.** Lab-scoped asks inject the lab `instructions`
   + the student's **latest `CodeSubmission` stdout/stderr only** — never the hidden
   `test_cases`/expected outputs (a test asserts the secret expected value never reaches
   the model prompt). The prompt forbids writing a full lab solution.
7. **No new permission.** Tutor chat lives under `me/*` (tenant-wrapped, since the group
   has no `tenant.user`); KB authoring under `can:manage-curriculum` (trainers hold it).
   Budget exhaustion surfaces as a `429 {error:{code:'ai_budget_exceeded'}}` the portal
   renders as a calm "resets tomorrow" state.

## Consequences

- Verified on a seeded scratch DB: `tutor:reindex` builds `knowledge_documents` +
  `knowledge_chunks` from the seeded curriculum; `AskTutor` (FakeAiClient) returns a
  cited answer + an `ai_event`; a Low-confidence answer opens an `academic` ticket
  routed to the batch trainer; a lab ask carries the student's run but not the hidden
  expected output. `TutorSeeder` ships one authored doc + a sample cited conversation.
- New: `knowledge_documents`/`knowledge_chunks`/`tutor_conversations`/`tutor_messages`
  (+ models/factories); `KnowledgeRetriever`, `CitationResolver`, `IngestKnowledgeDocument`,
  `RebuildTutorIndex`, `AskTutor`, the `tutor:reindex` command; `TutorController` +
  admin `KnowledgeController` (+ Requests/Resources); `tutor.v2.md`; `TutorConfidence`
  enum + `TicketCategory::Academic` + the `academic` support route; frontend `/tutor`
  chat + labs "ask for a hint" + admin Tutor-KB page.
- **Deferred (owner-visible, not gaps):** semantic embeddings (schema is embedding-ready);
  transcript/notes ingestion (P3.5 — folds into the same `knowledge_documents` store);
  spaced-repetition remediation + weekly AI study plan (§6.4, later); streaming answers
  (sync now); paraphrase-aware repeat detection (token-fingerprint v1). Standing ops note:
  production cron now also runs `tutor:reindex` (alongside `fees:run-ladder`,
  `conversions:run-nudges`, `support:check-sla`, `scores:recompute`). Next: **P3.4 —
  Assessment automation** (AI quiz generation + MCQ dispatch + AI grading), the second
  real gateway consumer.
