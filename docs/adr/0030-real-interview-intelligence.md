# ADR 0030 — P4.2 Real Interview Intelligence + transcript ingestion

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD §6.6 — the `real_interview_bank` and its ingestion
  pipeline; the data spine of "Built from real interviews" and the input to
  Curriculum Intelligence (§6.21). Builds directly on the P4.1 mock engine
  (ADR 0029).

## Decisions

**Source-agnostic pipeline, one honest state machine.** `interview_transcripts`
moves pending → transcribing → parsing → parsed | failed. Pasted text,
Parakeet exports and debrief notes parse immediately; .txt/.md/.vtt/.srt read
directly; .docx text-extracts deterministically (ZipArchive on
word/document.xml); zips fan out one child per .txt entry; audio/video queues
through a swappable `TranscriptionClient` (Null until a provider is
configured — it fails loudly with the operator's next step rather than
pretending). PDFs are stored and failed with a clear message until a real
extractor lands. No format silently loses data: every original is preserved.

**Anonymisation is layered and non-negotiable.** Uploads require a consent
checkbox (`consent_confirmed_at` NOT NULL, audit-logged with the uploader).
Originals are stored ENCRYPTED (`Crypt` blob on the s3 disk); the parser only
ever sees the machine-scrubbed working copy (`Anonymizer`: emails, phones,
URLs, handles). The parse prompt (`transcript_parse.v1`) additionally
redacts names to [CANDIDATE]/[INTERVIEWER]/[COMPANY], and every parsed field
is scrubbed AGAIN before bank entry — regexes don't hallucinate, so the
deterministic pass brackets the AI one.

**Dedupe IS the analytics.** Each parsed question gets a fingerprint
(sha1 of role + normalised phrasing, unique per tenant). A repeat bumps
`asked_count` + `last_seen_at` inside a row lock instead of duplicating —
that counter is the frequency signal behind bank analytics (topic/role/
monthly trend), gap-report weights, and mock question ranking. No separate
counting infrastructure.

**Humans gate the bank.** Parsed questions land as `pending`; only
placement-officer approval (`can:manage-placements` — first consumer of that
gate) makes them `approved`, and ONLY approved rows reach students: mock
prompts, gap reports and analytics all filter on approved. Batch-approve
covers explicit ids or all high-confidence parses (parser self-assessment).

**Mocks now draw from the bank.** `mock_interview.v2` (v1 kept immutable per
prompt-versioning policy) receives up to five approved, not-yet-asked
questions for the blueprint's course/role, most-asked first, and is told to
prefer them verbatim. Empty bank → "(none available)" → v2 behaves exactly
like v1, so the feature degrades to P4.1 with zero configuration.

**The gap report is deterministic.** Real weights = approved-bank topic
frequencies for the student's blueprint; student side = best completed mock's
per-competency scores (loose word-match between topic tags and competency
names). Topics untested or below 70 (the human-gate bar) flag as gaps. No AI
call, no token cost, always current; renders on /mock above past interviews.

## Consequences

- 16 Pest tests in `tests/Feature/Interviews/` cover consent gating, PII
  scrub (both passes), dedupe, parse failure, audio via fake client, the
  no-provider failure path, zip fan-out, docx extraction, pdf refusal,
  permission boundaries, review queue + batch-approve, analytics weighting,
  cross-tenant isolation, bank→mock prompt injection and the gap report
  (suite: 568).
- `InterviewBankSeeder` ships generic role-tagged demo questions (no company
  claims — never fabricate "asked at X") so the admin page, mock sourcing and
  gap report demo out of the box.
- Founder inputs that unlock full power: a transcription provider credential
  (audio/video), and real Parakeet exports. P4.7 Curriculum Intelligence
  reads `asked_count` trends from here.
