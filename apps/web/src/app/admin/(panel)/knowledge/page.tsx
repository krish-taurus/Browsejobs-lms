"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type KnowledgeDoc = {
  id: number;
  title: string;
  source_type: string;
  body: string;
  is_active: boolean;
  chunk_count?: number;
  editable: boolean;
};

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminKnowledgePage() {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<number | null>(null);
  const [form, setForm] = useState({ title: "", body: "" });

  const docs = useQuery({
    queryKey: ["admin", "knowledge"],
    queryFn: () => apiJson<{ data: KnowledgeDoc[] }>("/api/v1/admin/knowledge"),
  });

  const refresh = () => void queryClient.invalidateQueries({ queryKey: ["admin", "knowledge"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");
  const reset = () => { setForm({ title: "", body: "" }); setEditing(null); setError(null); };

  const save = useMutation({
    mutationFn: () =>
      apiJson(editing ? `/api/v1/admin/knowledge/${editing}` : "/api/v1/admin/knowledge", {
        method: editing ? "PATCH" : "POST",
        body: JSON.stringify({ title: form.title, body: form.body, is_active: true }),
      }),
    onSuccess: () => { reset(); refresh(); },
    onError,
  });

  const remove = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/knowledge/${id}`, { method: "DELETE" }),
    onSuccess: refresh,
    onError,
  });

  const reindex = useMutation({
    mutationFn: () => apiJson("/api/v1/admin/knowledge/reindex", { method: "POST", body: JSON.stringify({}) }),
    onSuccess: refresh,
    onError,
  });

  const list = docs.data?.data ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">AI Tutor</p>
          <h1 className="display mt-2 text-3xl text-ink">Knowledge base</h1>
          <p className="mt-1 text-sm text-muted">What the tutor can cite. Course, program and lab content is ingested automatically; add curated notes below.</p>
        </div>
        <button
          onClick={() => reindex.mutate()}
          disabled={reindex.isPending}
          className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50"
        >
          {reindex.isPending ? "Rebuilding…" : "Rebuild from content"}
        </button>
      </div>

      {/* Author / edit a manual document */}
      <div className="mt-6 rounded-2xl border border-line bg-white p-5">
        <p className="text-sm font-semibold text-ink">{editing ? "Edit document" : "Add a document"}</p>
        <input className={`${inputCls} mt-3`} placeholder="Title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        <textarea className={`${inputCls} mt-3`} rows={5} placeholder="Content the tutor can draw on…" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} />
        {error && <p className="mt-2 text-sm text-warn">{error}</p>}
        <div className="mt-3 flex gap-2">
          <button onClick={() => save.mutate()} disabled={save.isPending || !form.title.trim() || !form.body.trim()} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {save.isPending ? "Saving…" : editing ? "Save changes" : "Add document"}
          </button>
          {editing && <button onClick={reset} className="rounded-full border border-line px-4 py-2 text-sm text-ink hover:bg-paper">Cancel</button>}
        </div>
      </div>

      {/* Documents */}
      {docs.isLoading ? (
        <div className="mt-6 space-y-2">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : list.length === 0 ? (
        <div className="mt-6"><EmptyState title="No documents yet" body="Rebuild from content to ingest your courses and labs, or add a curated note above." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {list.map((d) => (
            <div key={d.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{d.title}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">{d.source_type} · {d.chunk_count ?? 0} chunks</p>
              </div>
              {d.editable ? (
                <>
                  <button onClick={() => { setEditing(d.id); setForm({ title: d.title, body: d.body }); setError(null); }} className="text-xs font-semibold text-trust hover:underline">Edit</button>
                  <button onClick={() => remove.mutate(d.id)} disabled={remove.isPending} className="text-xs font-semibold text-warn hover:underline">Delete</button>
                </>
              ) : (
                <span className="mono text-[10px] uppercase tracking-widest text-muted">ingested</span>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
