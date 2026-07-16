# ADR 0007 — P2.2 Payments + EMI: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.8 (Fee Management + EMI Engine), §5 Stage 3, §14.4
  (fee model), `docs/BUILD-PLAYBOOK.md` P2.2

## Context

P2.2 builds the money layer on top of the P2.1 CRM. It starts from zero — no
payment models, migrations, events, Razorpay client, or config existed. This ADR
records the decisions taken so later slices (P2.3 dunning, P2.4 messaging, the
student dashboard) know the seams. Founder-confirmed choices: GST **inclusive**
@18%, EMI **links + schedule now** with AutoPay deferred, **voucher manager
deferred** (discount field kept).

## Decisions

1. **Razorpay via an interface, not the SDK.** `App\Support\Razorpay\RazorpayClient`
   + `HttpRazorpayClient` (Laravel `Http`, Basic auth) + `FakeRazorpayClient`
   (tests/dev), bound by a config factory in `AppServiceProvider`. Mirrors the
   Zoom client. Tests never touch the network. UPI AutoPay/eMandate creation will
   extend this interface later; **not built now** (needs live sandbox creds).

2. **The webhook is the source of truth, idempotent.** `POST /webhooks/razorpay`
   is HMAC-verified by `razorpay.signed` middleware (`X-Razorpay-Signature` =
   HMAC-SHA256(rawBody, webhook_secret)); `ReconcilePayment` matches the
   instalment by order / payment-link id and processes `payment.captured` once
   (unique `razorpay_payment_id` + captured-status guard). Every delivery is
   recorded in `webhooks_log`. A client-side verify handshake
   (`verifyPaymentSignature`) exists on the client for the eventual checkout, but
   reconciliation authority is the webhook.

3. **Money is paise integers, server-owned.** Amounts come from `config/fees.php`
   (registration ₹30,000; EMI 1/2/3), never from the client. **GST inclusive
   @18%**: `taxable = round(total/1.18)`, `gst = total − taxable`,
   `cgst = intdiv(gst,2)`, `sgst = gst − cgst` — parts always sum to the total
   (`GstCalculator`). GSTIN `[GST]` + `[CIN]` remain founder placeholders.

4. **EMI schedule**: instalment 1 due at checkout, the rest same calendar day
   monthly with month-end clamping (`addMonthsNoOverflow`). `discount_paise` is
   applied to instalment 1 (stand-in for the deferred voucher manager); the
   schedule is previewable before confirm (`PreviewSchedule`).

5. **Reserved→Enrolled + CRM flip on instalment 1** runs as a queued idempotent
   job (`FlipEnrolment`): sets `batch_members.status`/`enrolled_at`, and moves
   the matching CRM lead to the `enrolled` stage. **The lead is correlated by
   normalized phone / email** (`PhoneNormalizer`) — there is no FK from `leads`
   to `users`. `MoveLeadStage` no-ops if already there. This closes the
   `LeadStageChanged` seam ADR 0006 left for P2.2.

6. **Receipts render via a `ReceiptRenderer` interface** — branded HTML (Blade)
   stored on the documents disk now, in a queued `RenderReceipt` job; the PRD's
   WeasyPrint pipeline binarizes to PDF later behind the same interface.

7. **Out of scope, deliberately deferred:** the fee-gate/dunning ladder + soft/
   hard blocks + real `FeeGate` (all **P2.3** — `FeeGate` stays `AllowAllFeeGate`,
   asserted by a test); UPI AutoPay/eMandate (interface stub); the voucher
   manager (discount field only); WhatsApp/email delivery of payment links
   (**P2.4** — links are stored/returned now); the in-portal student fee widget
   (student-dashboard milestone — students pay via the Razorpay-hosted link).

## Consequences

- A bootcamp attendee can be raised a plan, sent a link, and — on capture —
  auto-enrolled with their CRM stage advanced, zero manual steps.
- New `manage-fees` permission (granted to admin) gates the payments API; the
  seeded permission count moved 12 → 13 (test updated).
- SQLite (test DB) can't add FK constraints via `ALTER TABLE`, but all payments
  tables are fresh creates, so every FK is real.
- Real use is blocked only on founder inputs (Razorpay sandbox keys, GSTIN/CIN,
  WeasyPrint CSS), not on code; tests + demo stay fully mocked.
