"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";
import { EmptyState } from "@/components/ui/EmptyState";

type Voucher = {
  id: number;
  name: string;
  type: "flat" | "percent";
  value: number;
  max_discount_paise: number | null;
  valid_days: number | null;
  per_user_limit: number;
  is_review_reward: boolean;
  active: boolean;
  issued_count?: number;
};

type Analytics = { issued: number; applied: number; discount_paise: number };

function Tile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[14px] border border-line bg-white p-5">
      <div className="mono text-2xl text-ink">{value}</div>
      <p className="mt-1 text-sm text-muted">{label}</p>
    </div>
  );
}

function describe(v: Voucher): string {
  return v.type === "flat" ? `${formatPaise(v.value)} off` : `${(v.value / 100).toFixed(0)}% off`;
}

const inputCls = "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminVouchersPage() {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({
    name: "",
    type: "flat" as "flat" | "percent",
    amount: "", // ₹ for flat, % for percent
    valid_days: "30",
    per_user_limit: "1",
    is_review_reward: false,
  });

  const vouchers = useQuery({
    queryKey: ["admin", "vouchers"],
    queryFn: () => apiJson<{ data: Voucher[] }>("/api/v1/admin/vouchers"),
  });
  const analytics = useQuery({
    queryKey: ["admin", "vouchers", "analytics"],
    queryFn: () => apiJson<{ data: Analytics }>("/api/v1/admin/vouchers/analytics"),
  });

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ["admin", "vouchers"] });
  };
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const create = useMutation({
    mutationFn: () => {
      const amount = Number(form.amount);
      // Flat vouchers store paise; percent vouchers store basis points.
      const value = form.type === "flat" ? Math.round(amount * 100) : Math.round(amount * 100);
      return apiJson("/api/v1/admin/vouchers", {
        method: "POST",
        body: JSON.stringify({
          name: form.name,
          type: form.type,
          value,
          valid_days: Number(form.valid_days) || null,
          per_user_limit: Number(form.per_user_limit) || 1,
          is_review_reward: form.is_review_reward,
        }),
      });
    },
    onSuccess: () => {
      setForm({ name: "", type: "flat", amount: "", valid_days: "30", per_user_limit: "1", is_review_reward: false });
      setError(null);
      refresh();
    },
    onError,
  });

  const deactivate = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/vouchers/${id}`, { method: "DELETE" }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  const a = analytics.data?.data;
  const list = vouchers.data?.data ?? [];

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Payments</p>
      <h1 className="display mt-2 text-3xl text-ink">Vouchers</h1>

      <div className="mt-8 grid gap-4 sm:grid-cols-3">
        <Tile label="Vouchers issued" value={a ? String(a.issued) : "—"} />
        <Tile label="Applied to plans" value={a ? String(a.applied) : "—"} />
        <Tile label="Total discounted" value={a ? formatPaise(a.discount_paise) : "—"} />
      </div>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error} <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {/* Create form */}
      <div className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-muted">New voucher</p>
        <form
          onSubmit={(e) => { e.preventDefault(); create.mutate(); }}
          className="mt-3 grid gap-2.5 sm:grid-cols-2"
        >
          <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Name" className={inputCls} />
          <div className="flex gap-2">
            <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value as "flat" | "percent" })} className={`${inputCls} flex-1`}>
              <option value="flat">₹ flat</option>
              <option value="percent">% percent</option>
            </select>
            <input
              required type="number" min="1" value={form.amount}
              onChange={(e) => setForm({ ...form, amount: e.target.value })}
              placeholder={form.type === "flat" ? "₹ off" : "% off"} className={`${inputCls} flex-1`}
            />
          </div>
          <input type="number" min="1" value={form.valid_days} onChange={(e) => setForm({ ...form, valid_days: e.target.value })} placeholder="Valid days" className={inputCls} />
          <input type="number" min="1" value={form.per_user_limit} onChange={(e) => setForm({ ...form, per_user_limit: e.target.value })} placeholder="Per-user limit" className={inputCls} />
          <label className="flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" checked={form.is_review_reward} onChange={(e) => setForm({ ...form, is_review_reward: e.target.checked })} className="h-4 w-4 rounded border-line text-trust" />
            Auto-issue on testimonial approval
          </label>
          <div className="sm:col-span-2">
            <button disabled={create.isPending || !form.name || !form.amount} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
              {create.isPending ? "Creating…" : "Create voucher"}
            </button>
          </div>
        </form>
      </div>

      {/* List */}
      {vouchers.isLoading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : list.length === 0 ? (
        <div className="mt-6"><EmptyState title="No vouchers yet" body="Create a voucher above. Flag one as the review reward to auto-issue it when a testimonial is approved." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {list.map((v) => (
            <div key={v.id} className="flex flex-wrap items-center gap-3 px-5 py-3.5">
              <span className="min-w-40 flex-1">
                <span className="block font-semibold text-ink">{v.name}</span>
                <span className="mono block text-xs text-muted">{describe(v)} · {v.valid_days ?? "∞"}d · {v.issued_count ?? 0} issued</span>
              </span>
              {v.is_review_reward && (
                <span className="mono rounded-full bg-verify-bg px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-verify">review reward</span>
              )}
              <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${v.active ? "bg-sky text-deep" : "bg-paper text-muted"}`}>
                {v.active ? "active" : "inactive"}
              </span>
              {v.active && (
                <button onClick={() => deactivate.mutate(v.id)} className="text-xs font-semibold text-warn hover:underline">
                  Deactivate
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
