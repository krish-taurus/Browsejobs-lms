"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type License = {
  id: number;
  label: string;
  zoom_user_id: string;
  active: boolean;
};

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminZoomPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ label: "", zoom_user_id: "" });

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "zoom-licenses"],
    queryFn: () => apiJson<{ data: License[] }>("/api/v1/admin/zoom-licenses"),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "zoom-licenses"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const create = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/zoom-licenses", {
        method: "POST",
        body: JSON.stringify({ label: form.label, zoom_user_id: form.zoom_user_id }),
      }),
    onSuccess: () => { setForm({ label: "", zoom_user_id: "" }); setError(null); void refresh(); },
    onError,
  });

  const toggle = useMutation({
    mutationFn: (l: License) =>
      apiJson(`/api/v1/admin/zoom-licenses/${l.id}`, {
        method: "PUT",
        body: JSON.stringify({ label: l.label, zoom_user_id: l.zoom_user_id, active: !l.active }),
      }),
    onSuccess: () => { setError(null); void refresh(); },
    onError,
  });

  const remove = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/zoom-licenses/${id}`, { method: "DELETE" }),
    onSuccess: () => void refresh(),
    onError,
  });

  const licenses = data?.data ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Live classes</p>
      <h1 className="display mt-2 text-3xl text-ink">Zoom licenses</h1>
      <p className="mt-1 text-sm text-muted">
        Each license is a Zoom host account. Licenses rotate automatically: every class claims
        whichever license is free at its time slot, so two licenses cover any two overlapping
        classes — no matter which trainer is teaching. Nothing is dedicated to one person.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <form onSubmit={(e) => { e.preventDefault(); create.mutate(); }} className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-muted">Add a license</p>
        <div className="mt-3 flex flex-wrap gap-2.5">
          <input required value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} placeholder="Label, e.g. Host 1" className={`${inputCls} min-w-40 flex-1`} />
          <input required value={form.zoom_user_id} onChange={(e) => setForm({ ...form, zoom_user_id: e.target.value })} placeholder="Zoom user / email" className={`${inputCls} min-w-52 flex-1`} />
        </div>
        <button disabled={create.isPending || !form.label || !form.zoom_user_id} className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {create.isPending ? "Adding…" : "Add license"}
        </button>
      </form>

      {isLoading ? (
        <div className="mt-4 space-y-2">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : licenses.length === 0 ? (
        <div className="mt-4"><EmptyState title="No Zoom licenses yet" body="Add one per host account so concurrent classes don't clash on a single Zoom user." /></div>
      ) : (
        <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
          {licenses.map((l) => (
            <div key={l.id} className="flex flex-wrap items-center gap-3 px-5 py-4">
              <span className="min-w-40 flex-1">
                <span className="block font-semibold text-ink">{l.label}</span>
                <span className="mono block text-xs text-muted">{l.zoom_user_id}</span>
              </span>
              <span className={`mono rounded-full px-2.5 py-1 text-[10px] uppercase tracking-widest ${l.active ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                {l.active ? "In rotation" : "Paused"}
              </span>
              <button onClick={() => toggle.mutate(l)} className="text-xs font-semibold text-trust hover:underline">
                {l.active ? "Pause" : "Resume"}
              </button>
              <button onClick={() => remove.mutate(l.id)} className="text-xs font-semibold text-warn hover:underline">Delete</button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
