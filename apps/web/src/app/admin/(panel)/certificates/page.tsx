"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Certificate = {
  id: number;
  code: string;
  number: string;
  student: string | null;
  course: string | null;
  issued_on: string | null;
  status: string;
  revoked: boolean;
  verify_url: string;
};

const inputCls = "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminCertificatesPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ user_id: "", course_id: "" });

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "certificates"],
    queryFn: () => apiJson<{ data: Certificate[] }>("/api/v1/admin/certificates"),
  });
  const certs = data?.data ?? [];

  const refresh = () => void qc.invalidateQueries({ queryKey: ["admin", "certificates"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const issue = useMutation({
    mutationFn: () => apiJson("/api/v1/admin/certificates", { method: "POST", body: JSON.stringify({ user_id: Number(form.user_id), course_id: Number(form.course_id) }) }),
    onSuccess: () => { setForm({ user_id: "", course_id: "" }); setError(null); refresh(); },
    onError,
  });
  const revoke = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/certificates/${id}/revoke`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: refresh,
    onError,
  });

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Records</p>
      <h1 className="display mt-2 text-3xl text-ink">Certificates</h1>
      <p className="mt-1 text-sm text-muted">Certificates auto-issue on course completion. Issue one manually or revoke below.</p>

      {/* Manual issue */}
      <div className="mt-6 rounded-2xl border border-line bg-white p-5">
        <p className="text-sm font-semibold text-ink">Issue manually</p>
        <div className="mt-3 flex flex-wrap gap-2">
          <input className={inputCls} placeholder="Student user ID" value={form.user_id} onChange={(e) => setForm({ ...form, user_id: e.target.value })} />
          <input className={inputCls} placeholder="Course ID" value={form.course_id} onChange={(e) => setForm({ ...form, course_id: e.target.value })} />
          <button onClick={() => issue.mutate()} disabled={issue.isPending || !form.user_id || !form.course_id} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {issue.isPending ? "Issuing…" : "Issue"}
          </button>
        </div>
        {error && <p className="mt-2 text-sm text-warn">{error}</p>}
      </div>

      {isLoading ? (
        <div className="mt-6 space-y-2">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : certs.length === 0 ? (
        <div className="mt-6"><EmptyState title="No certificates yet" body="Certificates issued automatically or by hand appear here." /></div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {certs.map((c) => (
            <div key={c.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{c.student ?? "Student"} · {c.course ?? "—"}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">{c.number} · {c.issued_on ?? "—"}</p>
              </div>
              {c.revoked ? (
                <span className="mono rounded-full bg-warn/10 px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-warn">revoked</span>
              ) : (
                <>
                  <a href={c.verify_url} target="_blank" rel="noreferrer" className="text-xs font-semibold text-trust hover:underline">Verify</a>
                  <button onClick={() => revoke.mutate(c.id)} disabled={revoke.isPending} className="text-xs font-semibold text-warn hover:underline">Revoke</button>
                </>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
