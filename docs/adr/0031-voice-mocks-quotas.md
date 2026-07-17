# ADR 0031 — P4.3 Voice mocks + quota enforcement

- **Status:** Accepted
- **Date:** 2026-07-17
- **Context:** PRD §6.6 (voice mode) + §6.17 (metered quotas). Completes the
  mock-interview stack: P4.1 engine (ADR 0029) + P4.2 bank (ADR 0030) +
  this transport/monetization layer.

## Decisions

**Voice is a transport, not a feature fork.** A voice session is the same
`mock_interviews` row (`mode='voice'`, ADR 0029) plus provider columns
(`provider_session_id`, `join_url`, `duration_seconds`, `cost_micros`). The
end-of-call transcript becomes ordinary `mock_turns`, then flows through the
UNCHANGED P4.1 pipeline — `FinishMockInterview` scorecard, points, badge,
PRI blend, human gate — and the P4.2 gap report reads voice scorecards for
free. Text practice stays flag-gated; voice is quota-gated and needs no flag.

**The wallet was already the quota — P4.3 just spends it.** P2.8 seeds
included credits per enrolment type (5 live / 2 self-paced), grants +1 per
module completed (capped), and settles paid packs (₹249/1, ₹599/3 products)
into the same wallet. `StartVoiceMock` consumes 1 credit up front; an empty
wallet answers **402 with the live top-up products** (one-tap: the existing
`me/purchases` Razorpay flow). No parallel quota system exists.

**A student never pays for a call that didn't happen.** Provider-create
failure → row deleted + credit refunded (verified live against the Null
client). Call ends with fewer than `mocks.min_answers` candidate turns, or
the provider reports failure → status **`abandoned`** + refund
(`voice_refund:{id}`) + no scorecard — an ungraded call can never mint
points or move PRI. Refunds and consumes are one auditable
`credit_transactions` ledger.

**The 10-minute cap travels with the session.** `mocks.voice.max_seconds`
(600) goes to the provider as the hard duration limit, and the interviewer
persona is instructed to wrap up gracefully in the final minute rather than
cut the candidate off. The persona carries the same compliance rules as the
text prompt (adaptive, neutral, no job/salary promises).

**Provider-agnostic behind one interface.** `VoiceMockClient` —
`HttpVapiClient` when `VAPI_API_KEY` is set (inline assistant, metadata,
webhook target), `NullVoiceMockClient` otherwise (fails loudly, credit
refunded), `FakeVoiceMockClient` in tests. Retell or any similar provider
slots in without touching the pipeline. Webhooks land at `webhooks/voice`
behind `voice.signed` (constant-time shared-secret check, rejected unsigned
— same policy as Zoom/Razorpay/WhatsApp) and are idempotent per delivery.

**Session cost is margin data.** Every completed call logs an `ai_events`
row (`model: voice-session`, provider cost in micros, duration in meta) —
voice spend sits in the same ledger the admin AI-usage page already reads.

## Consequences

- 8 Pest tests in `tests/Feature/Mocks/VoiceMockTest.php`: consume/resume
  without double-charge, 402 + top-ups, failed-create refund, unsigned
  webhook rejection, full end-of-call → scorecard/points/cost (idempotent),
  short-call abandon + refund, failed-call refund, summary payload
  (suite: 576).
- Launch checklist: set `VAPI_API_KEY` + `VAPI_WEBHOOK_SECRET`, point the
  provider at `/api/webhooks/voice` — everything else is already live.
- P4.4 (mentor scheduling) reuses the `mentor` wallet the same way.
