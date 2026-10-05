"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";
import { EmptyState } from "@/components/ui/EmptyState";

type Product = {
  id: number;
  sku: string;
  name: string;
  feature: string | null;
  kind: string;
  price_paise: number;
  grant_amount: number;
  period_days: number | null;
  mock_bonus_amount: number | null;
  job_application_bonus_amount: number | null;
  wider_market_job_limit: number | null;
};

type Wallet = { feature: string; balance: number };
type Entitlement = { key: string; kind: string; expires_at: string | null };
type CareerBoostStatus = {
  active: boolean;
  expires_at: string | null;
  mocks_remaining: number;
  applications_remaining: number;
  wider_market_job_limit: number;
};
type Store = { products: Product[]; wallets: Wallet[]; entitlements: Entitlement[]; career_boost: CareerBoostStatus };

const FEATURE_LABEL: Record<string, string> = {
  cv: "CV credits",
  voice_mock: "Voice mocks",
  mentor: "Mentor sessions",
};

function CareerBoostCard({ product, busy, onBuy }: { product: Product; busy: boolean; onBuy: () => void }) {
  const features = [
    `+${product.mock_bonus_amount ?? 0} AI mock interview attempts`,
    `+${product.job_application_bonus_amount ?? 0} job applications`,
    `+${product.grant_amount} CV creates`,
    `See up to ${product.wider_market_job_limit ?? 0} matched jobs`,
    `Valid for ${product.period_days ?? 30} days`,
  ];

  return (
    <div className="flex flex-col rounded-[16px] border border-line bg-white p-5">
      <p className="text-sm font-semibold text-trust">{product.name}</p>
      <p className="display mt-1 text-2xl text-ink">{formatPaise(product.price_paise)}</p>
      <hr className="my-3 border-line" />
      <p className="mono text-[10px] uppercase tracking-widest text-muted">Includes</p>
      <ul className="mt-2 flex-1 space-y-2">
        {features.map((f) => (
          <li key={f} className="flex items-start gap-2 text-sm text-ink">
            <span aria-hidden className="mt-0.5 text-verify">✓</span>
            {f}
          </li>
        ))}
      </ul>
      <button
        onClick={onBuy}
        disabled={busy}
        className="mt-4 w-full rounded-full bg-trust py-2.5 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50"
      >
        Buy now
      </button>
    </div>
  );
}

export default function StorePage() {
  const [store, setStore] = useState<Store | null>(null);
  const [loading, setLoading] = useState(true);
  const [banner, setBanner] = useState<{ kind: "ok" | "err"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    apiJson<{ data: Store }>("/api/v1/me/store")
      .then((r) => setStore(r.data))
      .catch(() => setStore(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function act(fn: () => Promise<unknown>, ok: string) {
    setBusy(true);
    setBanner(null);
    try { await fn(); setBanner({ kind: "ok", text: ok }); load(); }
    catch (err) { setBanner({ kind: "err", text: err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong." }); }
    finally { setBusy(false); }
  }

  const buy = (p: Product) =>
    act(() => apiJson("/api/v1/me/purchases", { method: "POST", body: JSON.stringify({ product_id: p.id }) }),
      "Payment started — complete it in the Razorpay window. Your balance updates once it's confirmed.");

  const subscribe = () =>
    act(() => apiJson("/api/v1/me/career-plus/subscribe", { method: "POST", body: JSON.stringify({}) }),
      "Career+ subscription started.");

  const cancel = () =>
    act(() => apiJson("/api/v1/me/career-plus/cancel", { method: "POST", body: JSON.stringify({}) }),
      "Career+ will not renew.");

  const careerActive = store?.entitlements.some((e) => e.key === "career_plus") ?? false;
  const boostProducts = store?.products.filter((p) => p.kind === "career_boost") ?? [];
  const boost = store?.career_boost;

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Store</p>
      <h1 className="display mt-2 text-3xl text-ink">Top-ups &amp; add-ons</h1>
      <p className="mt-2 text-sm text-muted">Everything essential stays included. These are extras beyond your generous quota.</p>

      {banner && (
        <p className={`mt-4 rounded-[10px] px-3 py-2 text-sm ${banner.kind === "ok" ? "bg-verify-bg text-verify" : "bg-warn/10 text-warn"}`}>
          {banner.text} <button onClick={() => setBanner(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {loading ? (
        <div className="mt-8 grid gap-4 sm:grid-cols-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-24 rounded-[14px]" />)}</div>
      ) : !store ? (
        <div className="mt-6"><EmptyState title="Store unavailable" body="Try again in a moment." /></div>
      ) : (
        <>
          {/* Wallets */}
          <div className="mt-8 grid gap-4 sm:grid-cols-3">
            {store.wallets.length === 0 ? (
              <div className="rounded-[14px] border border-line bg-white p-5 sm:col-span-3 text-sm text-muted">
                Your included quota unlocks as you complete modules.
              </div>
            ) : store.wallets.map((w) => (
              <div key={w.feature} className="rounded-[14px] border border-line bg-white p-5">
                <div className="mono text-2xl text-ink">{w.balance}</div>
                <p className="mt-1 text-sm text-muted">{FEATURE_LABEL[w.feature] ?? w.feature}</p>
              </div>
            ))}
          </div>

          {/* Career Boost */}
          {boostProducts.length > 0 && (
            <div className="mt-10">
              <p className="mono text-[11px] uppercase tracking-widest text-trust">Career Boost</p>
              <h2 className="display mt-1 text-xl text-ink">Move faster on Jobs for You</h2>
              <p className="mt-1 text-sm text-muted">
                Extra AI mock interviews, more job applications, more CV creates, and a wider Wider Market feed —
                bundled, time-boxed, on top of everything the free tier already gives you.
              </p>

              {boost?.active && (
                <div className="mt-4 rounded-[14px] border border-verify/30 bg-verify-bg p-4">
                  <p className="text-sm font-semibold text-verify">✓ Career Boost active</p>
                  <p className="mt-1 text-xs text-ink">
                    {boost.mocks_remaining} mock attempt{boost.mocks_remaining === 1 ? "" : "s"} · {boost.applications_remaining} application{boost.applications_remaining === 1 ? "" : "s"} left ·
                    sees up to {boost.wider_market_job_limit} jobs
                    {boost.expires_at && ` · expires ${new Date(boost.expires_at).toLocaleDateString("en-IN", { day: "numeric", month: "short" })}`}
                  </p>
                </div>
              )}

              <div className="mt-4 grid gap-4 sm:grid-cols-3">
                {boostProducts.map((p) => (
                  <CareerBoostCard key={p.id} product={p} busy={busy} onBuy={() => buy(p)} />
                ))}
              </div>
            </div>
          )}

          {/* Catalog */}
          <div className="mt-10 divide-y divide-line rounded-[14px] border border-line bg-white">
            {store.products.filter((p) => p.kind !== "self_paced" && p.kind !== "upgrade" && p.kind !== "career_boost").map((p) => (
              <div key={p.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
                <span className="min-w-40 flex-1">
                  <span className="block font-semibold text-ink">{p.name}</span>
                  <span className="mono block text-xs text-muted">
                    {p.kind === "subscription" ? `${formatPaise(p.price_paise)} / month` : `${formatPaise(p.price_paise)}${p.grant_amount ? ` · +${p.grant_amount}` : ""}`}
                  </span>
                </span>
                {p.kind === "subscription" ? (
                  careerActive ? (
                    <button onClick={cancel} disabled={busy} className="rounded-full border border-line px-4 py-1.5 text-sm text-ink hover:bg-paper disabled:opacity-50">Cancel</button>
                  ) : (
                    <button onClick={subscribe} disabled={busy} className="rounded-full bg-trust px-4 py-1.5 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50">Subscribe</button>
                  )
                ) : (
                  <button onClick={() => buy(p)} disabled={busy} className="rounded-full bg-trust px-4 py-1.5 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50">Buy</button>
                )}
              </div>
            ))}
          </div>

          {careerActive && (
            <p className="mt-3 text-xs text-verify">✓ Career+ active — continued AI coach, market pulse, monthly mock &amp; CV refresh.</p>
          )}
        </>
      )}
    </div>
  );
}
