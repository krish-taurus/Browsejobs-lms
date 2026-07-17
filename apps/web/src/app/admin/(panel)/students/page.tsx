"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type StudentBatch = {
  member_id: number;
  batch_id: number;
  number: string | null;
  course: string | null;
  status: string;
};
type Student = {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  batches: StudentBatch[];
};
type BatchRow = { id: number; number: string };

const STATUS_STYLES: Record<string, string> = {
  reserved: "bg-sky text-deep",
  payment_pending: "bg-amber/15 text-ink2",
  enrolled: "bg-verify-bg text-verify",
  completed: "bg-verify-bg text-verify",
};

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminCandidatesPage() {
  const qc = useQueryClient();
  const [q, setQ] = useState("");
  const [batchFilter, setBatchFilter] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [addingFor, setAddingFor] = useState<number | null>(null);
  const [addTarget, setAddTarget] = useState("");

  const params = new URLSearchParams();
  if (q.trim()) params.set("q", q.trim());
  if (batchFilter) params.set("batch_id", batchFilter);
  const qs = params.toString();

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "students", qs],
    queryFn: () => apiJson<{ data: Student[] }>(`/api/v1/admin/students${qs ? `?${qs}` : ""}`),
  });
  const batches = useQuery({
    queryKey: ["admin", "batches"],
    queryFn: () => apiJson<{ data: BatchRow[] }>("/api/v1/admin/batches"),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "students"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const enroll = useMutation({
    mutationFn: ({ studentId, batchId }: { studentId: number; batchId: number }) =>
      apiJson(`/api/v1/admin/students/${studentId}/enrollments`, {
        method: "POST",
        body: JSON.stringify({ batch_id: batchId }),
      }),
    onSuccess: () => { setAddingFor(null); setAddTarget(""); setError(null); void refresh(); },
    onError,
  });

  const act = useMutation({
    mutationFn: ({ path, body }: { path: string; body: object }) =>
      apiJson(path, { method: "POST", body: JSON.stringify(body) }),
    onSuccess: () => { setError(null); void refresh(); },
    onError,
  });

  function move(b: StudentBatch, studentName: string) {
    const options = (batches.data?.data ?? []).filter((x) => x.id !== b.batch_id);
    const target = window.prompt(
      `Move ${studentName} from ${b.number} to which batch?\n` +
        options.map((x) => `${x.id}: ${x.number}`).join("\n"),
    );
    if (!target) return;
    const reason = window.prompt("Reason for the move?");
    if (!reason) return;
    act.mutate({ path: `/api/v1/admin/members/${b.member_id}/transfer`, body: { target_batch_id: Number(target), reason } });
  }

  function removeFrom(b: StudentBatch, studentName: string) {
    const reason = window.prompt(`Remove ${studentName} from ${b.number}? Reason:`);
    if (!reason) return;
    act.mutate({ path: `/api/v1/admin/members/${b.member_id}/remove`, body: { reason } });
  }

  const students = data?.data ?? [];

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">People</p>
      <h1 className="display mt-2 text-3xl text-ink">Candidates</h1>
      <p className="mt-1 text-sm text-muted">Every student across the institute. Filter by batch, or move and add students between batches.</p>

      <div className="mt-6 flex flex-wrap gap-2.5">
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, phone or email" className={`${inputCls} min-w-56 flex-1`} />
        <select value={batchFilter} onChange={(e) => setBatchFilter(e.target.value)} className={`${inputCls} w-52`}>
          <option value="">All batches</option>
          {batches.data?.data.map((b) => <option key={b.id} value={b.id}>{b.number}</option>)}
        </select>
      </div>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error} <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {isLoading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : students.length === 0 ? (
        <div className="mt-6"><EmptyState title="No candidates found" body="Adjust the search or batch filter, or add students from a batch page." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {students.map((s) => (
            <div key={s.id} className="px-5 py-4">
              <div className="flex flex-wrap items-start gap-3">
                <span className="display grid h-9 w-9 place-items-center rounded-full bg-ink text-xs text-white">
                  {s.name.charAt(0).toUpperCase()}
                </span>
                <span className="min-w-40 flex-1">
                  <span className="block font-semibold text-ink">{s.name}</span>
                  <span className="mono block text-xs text-muted">{s.phone ?? s.email ?? "—"}</span>
                  <span className="mt-2 flex flex-wrap gap-1.5">
                    {s.batches.length === 0 && <span className="text-xs text-muted">Not in any batch</span>}
                    {s.batches.map((b) => (
                      <span key={b.member_id} className="inline-flex items-center gap-1.5 rounded-full border border-line bg-paper px-2.5 py-1">
                        <span className="mono text-[11px] text-ink">{b.number}</span>
                        <span className={`mono rounded-full px-1.5 text-[9px] uppercase tracking-widest ${STATUS_STYLES[b.status] ?? "bg-paper text-muted"}`}>{b.status.replace("_", " ")}</span>
                        <button onClick={() => move(b, s.name)} className="text-[11px] font-semibold text-trust hover:underline">move</button>
                        <button onClick={() => removeFrom(b, s.name)} className="text-[11px] font-semibold text-warn hover:underline">remove</button>
                      </span>
                    ))}
                  </span>
                </span>
                <div className="shrink-0">
                  {addingFor === s.id ? (
                    <span className="flex items-center gap-1.5">
                      <select value={addTarget} onChange={(e) => setAddTarget(e.target.value)} className={`${inputCls} w-40 py-1.5`}>
                        <option value="">Choose batch…</option>
                        {(batches.data?.data ?? [])
                          .filter((x) => !s.batches.some((sb) => sb.batch_id === x.id))
                          .map((x) => <option key={x.id} value={x.id}>{x.number}</option>)}
                      </select>
                      <button
                        disabled={!addTarget || enroll.isPending}
                        onClick={() => enroll.mutate({ studentId: s.id, batchId: Number(addTarget) })}
                        className="rounded-full bg-trust px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                      >
                        Add
                      </button>
                      <button onClick={() => { setAddingFor(null); setAddTarget(""); }} className="text-xs text-muted hover:underline">cancel</button>
                    </span>
                  ) : (
                    <button onClick={() => { setAddingFor(s.id); setAddTarget(""); }} className="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust">
                      Add to batch
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
