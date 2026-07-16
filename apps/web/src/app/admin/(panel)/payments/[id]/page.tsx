"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";
import { type FeePlan, type Instalment } from "@/lib/payments";

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

const STATUS_STYLES: Record<string, string> = {
  pending: "bg-paper text-muted",
  paid: "bg-verify-bg text-verify",
  failed: "bg-warn/10 text-warn",
};

export default function FeePlanDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const planKey = ["admin", "payments", id];
  const { data, isLoading } = useQuery({
    queryKey: planKey,
    queryFn: () => apiJson<{ data: FeePlan }>(`/api/v1/admin/fee-plans/${id}`),
  });
  const plan = data?.data;

  const link = useMutation({
    mutationFn: (instalmentId: number) => apiJson(`/api/v1/admin/instalments/${instalmentId}/link`, { method: "POST" }),
    onSuccess: () => { setError(null); void queryClient.invalidateQueries({ queryKey: planKey }); },
    onError: (err) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not create link."),
  });

  if (isLoading) return <div className="mx-auto max-w-4xl space-y-3"><div className="shimmer h-24 rounded-[14px]" /><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!plan) return <p className="mx-auto max-w-4xl text-sm text-muted">Fee plan not found.</p>;

  const debit = (plan.ledger ?? []).filter((l) => l.direction === "debit").reduce((s, l) => s + l.amount_paise, 0);
  const credit = (plan.ledger ?? []).filter((l) => l.direction === "credit").reduce((s, l) => s + l.amount_paise, 0);
  const balance = debit - credit;

  return (
    <div className="mx-auto max-w-4xl">
      <Link href="/admin/payments" className="text-sm text-muted hover:text-ink">← Fee plans</Link>
      <div className="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="kicker text-trust">{plan.type === "emi" ? "EMI plan" : "Single payment"}</p>
          <h1 className="display mt-1 text-3xl text-ink">{plan.student?.name}</h1>
          <p className="mono mt-1 text-sm text-muted">{plan.batch?.number} · {plan.batch?.course?.name}</p>
        </div>
        <div className="text-right">
          <p className="mono text-2xl text-ink">{formatPaise(plan.net_payable_paise)}</p>
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Net payable</p>
        </div>
      </div>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {/* Schedule */}
      <section className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-muted">Instalment schedule</p>
        <div className="mt-3 divide-y divide-line">
          {(plan.instalments ?? []).map((inst: Instalment) => (
            <div key={inst.id} className="flex flex-wrap items-center gap-3 py-3">
              <span className="mono w-8 text-muted">#{inst.seq}</span>
              <span className="w-24 text-sm text-muted">{inst.due_on}</span>
              <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${STATUS_STYLES[inst.status]}`}>{inst.status}</span>
              <span className="mono flex-1 text-right font-semibold text-ink">{formatPaise(inst.amount_paise)}</span>
              {inst.status === "pending" && (
                inst.payment_link_url ? (
                  <a href={inst.payment_link_url} target="_blank" rel="noreferrer" className="mono text-xs text-trust hover:underline">Copy link ↗</a>
                ) : (
                  <button onClick={() => link.mutate(inst.id)} disabled={link.isPending} className="rounded-full border border-line px-3 py-1 text-xs font-medium text-ink hover:bg-paper disabled:opacity-50">
                    Generate link
                  </button>
                )
              )}
            </div>
          ))}
        </div>
      </section>

      <div className="mt-6 grid gap-6 md:grid-cols-2">
        {/* Ledger */}
        <section className="rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-muted">Ledger</p>
          <div className="mt-3 space-y-1.5 text-sm">
            {(plan.ledger ?? []).map((l) => (
              <div key={l.id} className="flex items-center justify-between gap-2">
                <span className="text-muted">{l.description}</span>
                <span className={`mono ${l.direction === "credit" ? "text-verify" : "text-ink"}`}>
                  {l.direction === "credit" ? "−" : "+"}{formatPaise(l.amount_paise)}
                </span>
              </div>
            ))}
          </div>
          <div className="mt-3 flex items-center justify-between border-t border-line pt-3 text-sm font-semibold">
            <span className="text-ink">Outstanding</span>
            <span className="mono text-ink">{formatPaise(balance)}</span>
          </div>
        </section>

        {/* Receipts */}
        <section className="rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-muted">Receipts</p>
          {(plan.receipts ?? []).length === 0 ? (
            <p className="mt-3 text-sm text-muted">No receipts yet — they appear once a payment is captured.</p>
          ) : (
            <ul className="mt-3 space-y-2 text-sm">
              {(plan.receipts ?? []).map((r) => (
                <li key={r.id} className="flex items-center justify-between gap-2">
                  <span className="mono text-ink">{r.number}</span>
                  <span className="flex items-center gap-3">
                    <span className="mono text-muted">{formatPaise(r.total_paise)}</span>
                    <a href={`${API_BASE}/api/v1/admin/receipts/${r.id}/download`} target="_blank" rel="noreferrer" className="text-xs text-trust hover:underline">
                      View ↗
                    </a>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}
