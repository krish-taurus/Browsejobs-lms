# ADR 0013 — P2.8 Entitlement engine + self-paced tier: design decisions

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** PRD v1.4 §6.17 (Freemium & Revenue Engine) + §6.3 (self-paced tier) + BUILD-PLAYBOOK P2.8 — the Phase-2 finale.

## Context

The whitelabel monetization backbone: one central Entitlement Service governing
every metered feature (wallets, quotas, credit transactions, quota_usages), an
admin monetization settings + product catalog (voucher-manager pattern), an in-app
one-tap purchase flow (Razorpay → GST receipt → grant), a refund workflow, the
self-paced recorded-course tier, a Career+ subscription, and a revenue-by-product
dashboard. The PRD's default pricing is seeded.

**Scope boundary:** the metered *consumers* — voice-mock drawdown (§6.6), CV-credit
consumption (§6.7), mentor booking (§6.11) — are **Phase 4** (P4.3/P4.5). P2.8
builds the engine + the *grant* side + the store/self-paced/subscription surfaces;
Phase-4 features call `EntitlementService::consume`.

**Founder-confirmed scope (this session):** Career+ is an **auto-recurring Razorpay
subscription**; checkout is **hosted order + webhook settlement**; refunds **claw
back credits + revoke access**.

## Decisions

1. **Generic engine, product-data-driven surfaces.** `EntitlementService`
   (`grantCredits`/`consumeCredits`/`clawbackCredits`/`balance`;
   `grantEntitlement`/`revokeEntitlement`/`hasEntitlement`; `recordUsage`;
   `includedQuota`/`grantIncludedQuota`; `settings`) over three stores:
   `credit_wallets` (countable — cv/voice_mock/mentor), `entitlements` (access —
   `self_paced:{batchId}`, `live:{batchId}`, `career_plus`), `quota_usages` (period
   metering; Phase-4 consumers write here). The store is data: `products` rows +
   a `monetization_settings` singleton. **One purchase pipeline settles them all;**
   only self-paced/upgrade/subscription add grant logic. Every mutation wraps in
   `TenantContext::run` so unscoped me/webhook callers are safe.
2. **Purchases are self-contained for GST** — they carry their own inclusive-GST
   breakup + receipt number/path (`BJ-P/{Y}/{n}`, rendered via the shared
   `receipts.purchase` Blade), decoupled from the fee `receipts` table (whose
   `payment_id`/`fee_plan_id` are NOT-NULL FKs) to avoid a SQLite nullable-change.
3. **Career+ = Razorpay subscription.** `subscriptions` row +
   `createSubscription`/`cancelSubscription` on the client. `subscription.charged`
   writes a settled `product_purchase` cycle (extends the `career_plus` entitlement
   + issues a receipt); `subscription.cancelled`/`halted` revokes. Client faked in
   tests.
4. **Hosted checkout + webhook settlement.** `StartPurchase` creates a Razorpay
   order (server-owned amount); the webhook settles on capture — the exact fee
   reconciliation path. `RazorpayWebhookController` now fans out: `ReconcilePayment`
   (fees, unchanged) → and if unmatched, `ReconcilePurchase` + `ReconcileSubscription`
   (each domain-scoped + idempotent). Production layers Checkout JS over the order.
5. **Self-paced tier.** A `self_paced` product bound to the completed source batch;
   buying grants `entitlement(self_paced:{batchId})` + a `BatchMember` with the new
   `enrolment_type=self_paced` (blocked from live join in `JoinLiveSession`, per
   §6.3) + the flat self-paced included quota. **Upgrade-to-live** buys the
   difference and flips the membership to `live`. `BatchType::SelfPaced` added;
   cohort auto-placement of an upgrader is a deferred roster action.
6. **Refund claws back + revokes** (founder): deduct unspent credits (floor 0),
   revoke access/subscription entitlements, un-enrol self-paced; `RazorpayClient::refund`
   (faked); `store.purchase_refunded` audit.
7. **Grants ride real events.** Voice quota unlocks 1-per-`ModuleCompleted` (capped
   at the included live max) — the "quota doubles as a progression reward" model —
   via the `GrantModuleReward` listener; self-paced buyers get their flat included
   quota on settle. (No bulk grant on paid enrolment — it would double-count.)
8. **New permission `manage-monetization`** gates products/settings/revenue/refund/
   publish-self-paced (admin). RolePermission count 15→16.

## Consequences

- Verified live on a seeded scratch DB: pack purchase → GST receipt (parts sum to
  total) + credits → consume → refund claws back to 0 + audit → self-paced published
  at 50% → bought → entitlement + live-join denied → Career+ subscribed + charged →
  active → revenue aggregates.
- New models/tables: `monetization_settings`, `products`, `product_purchases`,
  `credit_wallets`, `credit_transactions`, `entitlements`, `quota_usages`,
  `subscriptions`; `batch_members.enrolment_type`.
- **Deferred (owner-visible):** metered consumption/enforcement of voice mocks + CV
  credits (**Phase 4** — the consuming features call `EntitlementService::consume`);
  the Razorpay Checkout JS in-page modal (hosted order + webhook is wired now);
  auto-placement of a self-paced upgrader into a specific next live cohort (roster
  action). **This closes Phase 2.** Next phase: **P3.1 — AI gateway + telemetry**.
