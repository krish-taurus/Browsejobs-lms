"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";
import { openCheckout, type RazorpayOrder } from "@/lib/razorpay";
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
};

type Wallet = { feature: string; balance: number };
type Entitlement = { key: string; kind: string; expires_at: string | null };
type Store = { products: Product[]; wallets: Wallet[]; entitlements: Entitlement[] };

const FEATURE_LABEL: Record<string, string> = {
  cv: "CV credits",
  voice_mock: "Voice mocks",
  mentor: "Mentor sessions",
};

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

  /**
   * Create the order server-side, then actually open Razorpay Checkout. The
   * wallet is credited by the Razorpay webhook, not here — so on success we
   * just reload the store and tell the student it may take a moment.
   */
  async function buy(p: Product) {
    setBusy(true);
    setBanner(null);
    try {
      const { data } = await apiJson<{ data: RazorpayOrder }>("/api/v1/me/purchases", {
        method: "POST",
        body: JSON.stringify({ product_id: p.id }),
      });

      const outcome = await openCheckout(data);

      if (outcome === "paid") {
        setBanner({ kind: "ok", text: "Payment received — your balance updates as soon as Razorpay confirms it." });
        load();
      } else if (outcome === "dismissed") {
        setBanner({ kind: "err", text: "Payment cancelled — nothing was charged." });
      } else {
        setBanner({ kind: "err", text: "That payment didn't go through. No money was taken — please try again." });
      }
    } catch (err) {
      setBanner({
        kind: "err",
        text: err instanceof ApiError
          ? (err.firstError ?? err.message)
          : err instanceof Error ? err.message : "Something went wrong.",
      });
    } finally {
      setBusy(false);
    }
  }

  const subscribe = () =>
    act(() => apiJson("/api/v1/me/career-plus/subscribe", { method: "POST", body: JSON.stringify({}) }),
      "Career+ subscription started.");

  const cancel = () =>
    act(() => apiJson("/api/v1/me/career-plus/cancel", { method: "POST", body: JSON.stringify({}) }),
      "Career+ will not renew.");

  const careerActive = store?.entitlements.some((e) => e.key === "career_plus") ?? false;

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

          {/* Catalog */}
          <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
            {store.products.filter((p) => p.kind !== "self_paced" && p.kind !== "upgrade").map((p) => (
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
