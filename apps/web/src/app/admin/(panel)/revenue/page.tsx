"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";
import { EmptyState } from "@/components/ui/EmptyState";

type Revenue = {
  gross_paise: number;
  refunds_paise: number;
  net_paise: number;
  by_feature: { feature: string; count: number; gross_paise: number; refunds_paise: number }[];
};

type Purchase = {
  id: number;
  sku: string;
  feature: string | null;
  amount_paise: number;
  status: string;
  receipt_number: string | null;
  student: { name: string } | null;
};

function Tile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[14px] border border-line bg-white p-5">
      <div className="mono text-2xl text-ink">{value}</div>
      <p className="mt-1 text-sm text-muted">{label}</p>
    </div>
  );
}

const STATUS_STYLES: Record<string, string> = {
  captured: "bg-verify-bg text-verify",
  refunded: "bg-warn/10 text-warn",
  pending: "bg-sky text-deep",
  failed: "bg-paper text-muted",
};

export default function RevenuePage() {
  const qc = useQueryClient();
  const revenue = useQuery({ queryKey: ["admin", "revenue"], queryFn: () => apiJson<{ data: Revenue }>("/api/v1/admin/revenue") });
  const purchases = useQuery({ queryKey: ["admin", "revenue", "purchases"], queryFn: () => apiJson<{ data: Purchase[] }>("/api/v1/admin/revenue/purchases?status=captured") });

  const refund = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/purchases/${id}/refund`, { method: "POST", body: JSON.stringify({ reason: "Admin refund" }) }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["admin", "revenue"] });
      void qc.invalidateQueries({ queryKey: ["admin", "revenue", "purchases"] });
    },
  });

  const d = revenue.data?.data;

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Revenue engine</p>
      <h1 className="display mt-2 text-3xl text-ink">Revenue by product</h1>

      {revenue.isLoading || !d ? (
        <div className="mt-8 grid gap-4 sm:grid-cols-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-24 rounded-[14px]" />)}</div>
      ) : (
        <>
          <div className="mt-8 grid gap-4 sm:grid-cols-3">
            <Tile label="Gross" value={formatPaise(d.gross_paise)} />
            <Tile label="Refunds" value={formatPaise(d.refunds_paise)} />
            <Tile label="Net" value={formatPaise(d.net_paise)} />
          </div>

          <h2 className="display mt-10 text-xl text-ink">By product</h2>
          {d.by_feature.length === 0 ? (
            <div className="mt-4"><EmptyState title="No revenue yet" body="Captured purchases will break down here by product." /></div>
          ) : (
            <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
              <div className="flex items-center gap-3 px-5 py-2 text-[11px] uppercase tracking-widest text-muted">
                <span className="flex-1">Product</span><span className="mono w-16 text-right">Count</span><span className="mono w-28 text-right">Gross</span><span className="mono w-28 text-right">Refunds</span>
              </div>
              {d.by_feature.map((f) => (
                <div key={f.feature} className="flex items-center gap-3 px-5 py-3 text-sm">
                  <span className="flex-1 font-semibold text-ink">{f.feature.replace(/_/g, " ")}</span>
                  <span className="mono w-16 text-right text-muted">{f.count}</span>
                  <span className="mono w-28 text-right text-verify">{formatPaise(f.gross_paise)}</span>
                  <span className="mono w-28 text-right text-warn">{formatPaise(f.refunds_paise)}</span>
                </div>
              ))}
            </div>
          )}

          <h2 className="display mt-10 text-xl text-ink">Recent purchases</h2>
          <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
            {(purchases.data?.data ?? []).map((p) => (
              <div key={p.id} className="flex flex-wrap items-center gap-3 px-5 py-3.5 text-sm">
                <span className="min-w-32 flex-1">
                  <span className="block font-semibold text-ink">{p.student?.name ?? "—"}</span>
                  <span className="mono block text-xs text-muted">{p.sku}{p.receipt_number ? ` · ${p.receipt_number}` : ""}</span>
                </span>
                <span className="mono text-ink">{formatPaise(p.amount_paise)}</span>
                <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${STATUS_STYLES[p.status] ?? "bg-paper text-muted"}`}>{p.status}</span>
                {p.status === "captured" && (
                  <button
                    onClick={() => { if (window.confirm("Refund this purchase? Credits/access will be clawed back.")) refund.mutate(p.id); }}
                    className="text-xs font-semibold text-warn hover:underline"
                  >
                    Refund
                  </button>
                )}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
