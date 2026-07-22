"use client";

import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Manual/forced review requests (Platform Spec §3). Send the review link by hand
 * to a whole batch, a hand-picked set of students, or both — with an optional
 * force that bypasses the "already asked" guard.
 */

type BatchRow = { id: number; number: string; type: string; course: { name: string } };
type StudentRow = { id: number; name: string; email: string | null; phone: string | null };

const STAGES = [
  { value: "manual", label: "Manual (ad-hoc)" },
  { value: "masterclass", label: "After masterclass" },
  { value: "bootcamp", label: "After bootcamp" },
  { value: "enrolment", label: "After enrolment" },
  { value: "course_complete", label: "After course" },
  { value: "placement", label: "After placement" },
] as const;

export function ManualReviewRequest() {
  const [open, setOpen] = useState(false);
  const [stage, setStage] = useState<string>("manual");
  const [force, setForce] = useState(true);
  const [batchId, setBatchId] = useState<string>("");
  const [query, setQuery] = useState("");
  const [selected, setSelected] = useState<StudentRow[]>([]);
  const [result, setResult] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data: batches } = useQuery({
    queryKey: ["admin", "batches"],
    queryFn: () => apiJson<{ data: BatchRow[] }>("/api/v1/admin/batches"),
    enabled: open,
  });

  const { data: results, isFetching } = useQuery({
    queryKey: ["admin", "students", query],
    queryFn: () => apiJson<{ data: StudentRow[] }>(`/api/v1/admin/students?q=${encodeURIComponent(query)}`),
    enabled: open && query.trim().length >= 2,
  });

  const add = (s: StudentRow) => {
    setSelected((prev) => (prev.some((p) => p.id === s.id) ? prev : [...prev, s]));
    setQuery("");
  };
  const remove = (id: number) => setSelected((prev) => prev.filter((p) => p.id !== id));

  const send = useMutation({
    mutationFn: () =>
      apiJson<{ data: { sent: number; skipped: number; targeted: number } }>("/api/v1/admin/review-requests", {
        method: "POST",
        body: JSON.stringify({
          stage,
          force,
          batch_id: batchId ? Number(batchId) : undefined,
          user_ids: selected.map((s) => s.id),
        }),
      }),
    onSuccess: (res) => {
      setError(null);
      setResult(`Sent ${res.data.sent} of ${res.data.targeted} — ${res.data.skipped} skipped.`);
      setSelected([]);
      setBatchId("");
    },
    onError: (err) =>
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not send."),
  });

  const canSend = (!!batchId || selected.length > 0) && !send.isPending;

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust"
      >
        + Send a review request
      </button>
    );
  }

  return (
    <div className="rounded-[14px] border border-line bg-white p-5">
      <div className="flex items-center justify-between">
        <h2 className="font-semibold text-ink">Send a review request</h2>
        <button onClick={() => setOpen(false)} className="text-sm text-muted hover:text-ink">Close</button>
      </div>
      <p className="mt-1 text-sm text-muted">
        To a whole batch, hand-picked students, or both. They get the same one-tap review link.
      </p>

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <label className="block">
          <span className="mb-1 block text-sm font-medium text-ink">Stage tag</span>
          <select
            value={stage}
            onChange={(e) => setStage(e.target.value)}
            className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          >
            {STAGES.map((s) => (
              <option key={s.value} value={s.value}>{s.label}</option>
            ))}
          </select>
        </label>

        <label className="block">
          <span className="mb-1 block text-sm font-medium text-ink">Whole batch (optional)</span>
          <select
            value={batchId}
            onChange={(e) => setBatchId(e.target.value)}
            className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          >
            <option value="">— none —</option>
            {(batches?.data ?? []).map((b) => (
              <option key={b.id} value={b.id}>{b.number} · {b.course.name} ({b.type})</option>
            ))}
          </select>
        </label>
      </div>

      <div className="mt-4">
        <span className="mb-1 block text-sm font-medium text-ink">Add specific students</span>
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search by name, email or phone…"
          className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
        {query.trim().length >= 2 && (
          <div className="mt-2 max-h-44 overflow-auto rounded-[10px] border border-line">
            {isFetching ? (
              <p className="px-3 py-2 text-sm text-muted">Searching…</p>
            ) : (results?.data ?? []).length === 0 ? (
              <p className="px-3 py-2 text-sm text-muted">No matches.</p>
            ) : (
              (results?.data ?? []).map((s) => (
                <button
                  key={s.id}
                  onClick={() => add(s)}
                  className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-paper"
                >
                  <span className="text-ink">{s.name}</span>
                  <span className="mono text-xs text-muted">{s.phone ?? s.email ?? ""}</span>
                </button>
              ))
            )}
          </div>
        )}
        {selected.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-2">
            {selected.map((s) => (
              <span key={s.id} className="inline-flex items-center gap-1.5 rounded-full bg-sky px-3 py-1 text-sm text-deep">
                {s.name}
                <button onClick={() => remove(s.id)} aria-label={`Remove ${s.name}`} className="text-deep/70 hover:text-deep">✕</button>
              </span>
            ))}
          </div>
        )}
      </div>

      <label className="mt-4 flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" checked={force} onChange={(e) => setForce(e.target.checked)} className="h-4 w-4 rounded border-line text-trust" />
        <span>Send even if they&apos;ve already been asked (force)</span>
      </label>

      {error && <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
      {result && <p className="mt-3 rounded-[10px] bg-verify-bg px-3 py-2 text-sm text-verify">{result}</p>}

      <button
        onClick={() => send.mutate()}
        disabled={!canSend}
        className="mt-4 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50"
      >
        {send.isPending ? "Sending…" : "Send review requests"}
      </button>
    </div>
  );
}
