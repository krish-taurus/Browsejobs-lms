"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Route = {
  id: number;
  category: string;
  label: string;
  routing: string;
  support_team: string | null;
  first_response_minutes: number;
  resolution_minutes: number;
};

type Canned = { id: number; title: string; body: string; active: boolean };

/** A support policy document — what AI first-response answers students from. */
type SupportDoc = {
  id: number;
  title: string;
  category: string;
  body: string;
  is_active: boolean;
  chunks_count?: number;
};

const CATEGORIES = ["payments", "technical", "training", "academic", "mentorship", "interview_prep", "other"];

const inputCls = "rounded-[10px] border border-line bg-white px-2 py-1.5 text-sm text-ink outline-none focus:border-trust";

export default function SupportSettingsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ title: "", body: "" });
  const [doc, setDoc] = useState({ title: "", category: "payments", body: "" });

  const routes = useQuery({ queryKey: ["admin", "ticket-routes"], queryFn: () => apiJson<{ data: Route[] }>("/api/v1/admin/ticket-routes") });
  const canned = useQuery({ queryKey: ["admin", "canned"], queryFn: () => apiJson<{ data: Canned[] }>("/api/v1/admin/canned-responses") });
  const docs = useQuery({ queryKey: ["admin", "support-docs"], queryFn: () => apiJson<{ data: SupportDoc[] }>("/api/v1/admin/support-documents") });

  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const saveRoute = useMutation({
    mutationFn: (b: { id: number; body: object }) => apiJson(`/api/v1/admin/ticket-routes/${b.id}`, { method: "PUT", body: JSON.stringify(b.body) }),
    onSuccess: () => { setError(null); void qc.invalidateQueries({ queryKey: ["admin", "ticket-routes"] }); },
    onError,
  });

  const createCanned = useMutation({
    mutationFn: () => apiJson("/api/v1/admin/canned-responses", { method: "POST", body: JSON.stringify(form) }),
    onSuccess: () => { setForm({ title: "", body: "" }); void qc.invalidateQueries({ queryKey: ["admin", "canned"] }); },
    onError,
  });

  const deleteCanned = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/canned-responses/${id}`, { method: "DELETE" }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["admin", "canned"] }),
    onError,
  });

  const refreshDocs = () => qc.invalidateQueries({ queryKey: ["admin", "support-docs"] });

  const createDoc = useMutation({
    mutationFn: () => apiJson("/api/v1/admin/support-documents", { method: "POST", body: JSON.stringify(doc) }),
    onSuccess: () => { setError(null); setDoc({ title: "", category: "payments", body: "" }); void refreshDocs(); },
    onError,
  });

  const toggleDoc = useMutation({
    mutationFn: (d: SupportDoc) => apiJson(`/api/v1/admin/support-documents/${d.id}`, {
      method: "PUT",
      body: JSON.stringify({ title: d.title, category: d.category, body: d.body, is_active: !d.is_active }),
    }),
    onSuccess: () => { setError(null); void refreshDocs(); },
    onError,
  });

  const deleteDoc = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/support-documents/${id}`, { method: "DELETE" }),
    onSuccess: () => void refreshDocs(),
    onError,
  });

  return (
    <div className="mx-auto max-w-3xl">
      <Link href="/admin/support" className="text-sm text-trust hover:underline">← Tickets</Link>
      <h1 className="display mt-3 text-3xl text-ink">Support settings</h1>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {/* Routing + SLA */}
      <h2 className="display mt-8 text-xl text-ink">Routing &amp; SLA</h2>
      <p className="mt-1 text-sm text-muted">First-response and resolution targets are in minutes.</p>
      <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
        {routes.data?.data.map((r) => (
          <div key={r.id} className="flex flex-wrap items-center gap-3 px-5 py-3.5">
            <span className="min-w-[120px] flex-1">
              <span className="block font-semibold text-ink">{r.label}</span>
              <span className="mono block text-xs text-muted">{r.routing.replace(/_/g, " ")}{r.support_team ? ` · ${r.support_team}` : ""}</span>
            </span>
            <label className="flex items-center gap-1 text-xs text-muted">1st
              <input type="number" min={1} defaultValue={r.first_response_minutes} className={`${inputCls} w-20`}
                onBlur={(e) => { const v = Number(e.target.value); if (v && v !== r.first_response_minutes) saveRoute.mutate({ id: r.id, body: { first_response_minutes: v } }); }} />
            </label>
            <label className="flex items-center gap-1 text-xs text-muted">res
              <input type="number" min={1} defaultValue={r.resolution_minutes} className={`${inputCls} w-24`}
                onBlur={(e) => { const v = Number(e.target.value); if (v && v !== r.resolution_minutes) saveRoute.mutate({ id: r.id, body: { resolution_minutes: v } }); }} />
            </label>
          </div>
        ))}
      </div>

      {/* Support policy corpus — what AI first-response answers from */}
      <h2 className="display mt-10 text-xl text-ink">Policy answers</h2>
      <p className="mt-1 text-sm text-muted">
        What the AI first-response answers students from before they raise a ticket. It can only
        answer from what is written here — it never invents policy. Anything not written here
        reaches a human instead.
      </p>

      <form onSubmit={(e) => { e.preventDefault(); createDoc.mutate(); }} className="mt-4 rounded-[14px] border border-line bg-white p-4">
        <div className="flex flex-wrap gap-2">
          <input required value={doc.title} onChange={(e) => setDoc({ ...doc, title: e.target.value })} placeholder="Title, e.g. Batch transfer policy" className={`${inputCls} min-w-[200px] flex-1`} maxLength={200} />
          <select value={doc.category} onChange={(e) => setDoc({ ...doc, category: e.target.value })} className={`${inputCls} w-44`}>
            {CATEGORIES.map((c) => <option key={c} value={c}>{c.replace(/_/g, " ")}</option>)}
          </select>
        </div>
        <textarea required value={doc.body} onChange={(e) => setDoc({ ...doc, body: e.target.value })} rows={4} placeholder="Write the policy in plain words, as you would say it to a student. Separate points with a blank line." className={`${inputCls} mt-2 w-full`} />
        <button disabled={createDoc.isPending || !doc.title || !doc.body} className="mt-2 rounded-full bg-trust px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-50">
          {createDoc.isPending ? "Saving…" : "Add policy"}
        </button>
      </form>

      {docs.isLoading ? (
        <div className="mt-3 space-y-2">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : (docs.data?.data.length ?? 0) === 0 ? (
        <p className="mt-3 rounded-[14px] border border-line bg-paper px-5 py-4 text-sm text-muted">
          No policy answers yet, so nothing is deflected — every question goes to a human. Start with
          the ones you answer most: fees, refunds, rescheduling.
        </p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {docs.data?.data.map((d) => (
            <div key={d.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
              <span className="min-w-[160px] flex-1">
                <span className="block text-sm font-semibold text-ink">{d.title}</span>
                <span className="mono block text-[11px] uppercase tracking-widest text-muted">{d.category.replace(/_/g, " ")}</span>
                <span className="mt-1 block line-clamp-2 text-xs text-muted">{d.body}</span>
              </span>
              {!d.is_active && <span className="mono rounded-full bg-paper px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-muted">off</span>}
              <button onClick={() => toggleDoc.mutate(d)} className="text-xs font-semibold text-trust hover:underline">
                {d.is_active ? "Turn off" : "Turn on"}
              </button>
              <button onClick={() => deleteDoc.mutate(d.id)} className="text-xs font-semibold text-warn hover:underline">Delete</button>
            </div>
          ))}
        </div>
      )}

      {/* Canned responses */}
      <h2 className="display mt-10 text-xl text-ink">Canned responses</h2>
      <form onSubmit={(e) => { e.preventDefault(); createCanned.mutate(); }} className="mt-3 rounded-[14px] border border-line bg-white p-4">
        <input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Title" className={`${inputCls} w-full`} />
        <textarea required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} rows={2} placeholder="Body" className={`${inputCls} mt-2 w-full`} />
        <button disabled={createCanned.isPending || !form.title || !form.body} className="mt-2 rounded-full bg-trust px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-50">Add</button>
      </form>
      <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
        {canned.data?.data.map((c) => (
          <div key={c.id} className="flex items-start gap-3 px-5 py-3">
            <span className="flex-1">
              <span className="block text-sm font-semibold text-ink">{c.title}</span>
              <span className="block text-xs text-muted">{c.body}</span>
            </span>
            <button onClick={() => deleteCanned.mutate(c.id)} className="text-xs font-semibold text-warn hover:underline">Delete</button>
          </div>
        ))}
      </div>
    </div>
  );
}
