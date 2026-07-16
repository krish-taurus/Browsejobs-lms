"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";
import {
  type BatchOption,
  type Candidate,
  type FeePlan,
  type ScheduleRow,
} from "@/lib/payments";
import { EmptyState } from "@/components/ui/EmptyState";

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

const STATUS_STYLES: Record<string, string> = {
  active: "bg-sky text-deep",
  paid: "bg-verify-bg text-verify",
  cancelled: "bg-warn/10 text-warn",
  draft: "bg-paper text-muted",
};

export default function AdminPaymentsPage() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState("");
  const [showCreate, setShowCreate] = useState(false);
  const [banner, setBanner] = useState<{ kind: "ok" | "err"; text: string } | null>(null);

  const query = useMemo(() => (status ? `?status=${status}` : ""), [status]);
  const { data: plans, isLoading } = useQuery({
    queryKey: ["admin", "payments", { status }],
    queryFn: () => apiJson<{ data: FeePlan[] }>(`/api/v1/admin/fee-plans${query}`),
  });

  const refresh = () => void queryClient.invalidateQueries({ queryKey: ["admin", "payments"] });

  return (
    <div className="mx-auto max-w-5xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Payments</p>
          <h1 className="display mt-2 text-3xl text-ink">Fee plans</h1>
        </div>
        <button
          onClick={() => { setShowCreate(!showCreate); setBanner(null); }}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep"
        >
          New fee plan
        </button>
      </div>

      {banner && (
        <p className={`mt-4 rounded-[10px] px-3 py-2 text-sm ${banner.kind === "ok" ? "bg-verify-bg text-verify" : "bg-warn/10 text-warn"}`}>
          {banner.text}
        </p>
      )}

      {showCreate && (
        <CreatePlanPanel
          onDone={(msg) => { setShowCreate(false); setBanner({ kind: "ok", text: msg }); refresh(); }}
          onError={(msg) => setBanner({ kind: "err", text: msg })}
        />
      )}

      <div className="mt-6 flex items-center gap-3">
        <select value={status} onChange={(e) => setStatus(e.target.value)} className={inputCls}>
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="paid">Paid</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {isLoading ? (
        <div className="mt-6 space-y-3">
          {Array.from({ length: 5 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}
        </div>
      ) : plans && plans.data.length === 0 ? (
        <div className="mt-6">
          <EmptyState title="No fee plans yet" body="Create a plan for a Reserved batch member to raise their registration fee and send a payment link." />
        </div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {plans?.data.map((p) => (
            <Link key={p.id} href={`/admin/payments/${p.id}`} className="flex flex-wrap items-center gap-3 px-5 py-4 transition-colors hover:bg-paper">
              <span className="min-w-[150px] flex-1 font-semibold text-ink">{p.student?.name ?? "—"}</span>
              <span className="mono text-xs text-muted">{p.batch?.number}</span>
              <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">
                {p.type === "emi" ? "EMI" : "Single"}
              </span>
              <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${STATUS_STYLES[p.status] ?? "bg-paper text-muted"}`}>
                {p.status}
              </span>
              <span className="mono w-28 text-right text-sm text-ink">{formatPaise(p.net_payable_paise)}</span>
              <span className="text-trust">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

function CreatePlanPanel({ onDone, onError }: { onDone: (msg: string) => void; onError: (msg: string) => void }) {
  const [form, setForm] = useState({ batch_id: "", user_id: "", type: "single", emi_count: "3", discount_paise: "0" });

  const { data: batches } = useQuery({
    queryKey: ["admin", "payments", "batches"],
    queryFn: () => apiJson<{ data: BatchOption[] }>("/api/v1/admin/fee-plans/batches"),
  });
  const { data: candidates } = useQuery({
    queryKey: ["admin", "payments", "candidates", form.batch_id],
    queryFn: () => apiJson<{ data: Candidate[] }>(`/api/v1/admin/fee-plans/candidates?batch_id=${form.batch_id}`),
    enabled: !!form.batch_id,
  });

  const preview = useQuery({
    queryKey: ["admin", "payments", "preview", form.type, form.emi_count, form.discount_paise],
    queryFn: () =>
      apiJson<{ data: { total_paise: number; instalments: ScheduleRow[] } }>("/api/v1/admin/fee-plans/preview", {
        method: "POST",
        body: JSON.stringify({
          type: form.type,
          emi_count: form.type === "emi" ? Number(form.emi_count) : 1,
          discount_paise: Number(form.discount_paise) || 0,
        }),
      }),
  });

  const create = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/fee-plans", {
        method: "POST",
        body: JSON.stringify({
          batch_id: Number(form.batch_id),
          user_id: Number(form.user_id),
          type: form.type,
          emi_count: form.type === "emi" ? Number(form.emi_count) : 1,
          discount_paise: Number(form.discount_paise) || 0,
        }),
      }),
    onSuccess: () => onDone("Fee plan created."),
    onError: (err) => onError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not create plan."),
  });

  return (
    <div className="mt-4 rounded-[14px] border border-line bg-white p-5">
      <p className="kicker text-muted">New fee plan — schedule previewed before you confirm</p>
      <div className="mt-4 flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1 text-xs text-muted">Batch
          <select value={form.batch_id} onChange={(e) => setForm({ ...form, batch_id: e.target.value, user_id: "" })} className={inputCls}>
            <option value="">Select…</option>
            {batches?.data.map((b) => <option key={b.id} value={b.id}>{b.number} — {b.course?.name}</option>)}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-xs text-muted">Student (Reserved)
          <select value={form.user_id} onChange={(e) => setForm({ ...form, user_id: e.target.value })} className={inputCls} disabled={!form.batch_id}>
            <option value="">Select…</option>
            {candidates?.data.map((c) => <option key={c.user_id} value={c.user_id}>{c.name} · {c.phone}</option>)}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-xs text-muted">Plan
          <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className={inputCls}>
            <option value="single">Single payment</option>
            <option value="emi">EMI</option>
          </select>
        </label>
        {form.type === "emi" && (
          <label className="flex flex-col gap-1 text-xs text-muted">Instalments
            <select value={form.emi_count} onChange={(e) => setForm({ ...form, emi_count: e.target.value })} className={inputCls}>
              <option value="1">1</option><option value="2">2</option><option value="3">3</option>
            </select>
          </label>
        )}
      </div>

      {/* Schedule preview */}
      <div className="mt-5 rounded-[10px] border border-line bg-paper p-4">
        <p className="kicker text-muted">Schedule preview</p>
        {preview.isLoading ? (
          <div className="shimmer mt-3 h-10 rounded-[10px]" />
        ) : (
          <table className="mt-3 w-full text-sm">
            <tbody>
              {preview.data?.data.instalments.map((row) => (
                <tr key={row.seq} className="border-b border-line last:border-0">
                  <td className="py-1.5 text-muted">Instalment {row.seq}</td>
                  <td className="py-1.5 text-muted">{row.due_on}</td>
                  <td className="mono py-1.5 text-right font-semibold text-ink">{formatPaise(row.amount_paise)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <button
        onClick={() => create.mutate()}
        disabled={!form.batch_id || !form.user_id || create.isPending}
        className="mt-4 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
      >
        {create.isPending ? "Creating…" : "Confirm & create plan"}
      </button>
    </div>
  );
}
