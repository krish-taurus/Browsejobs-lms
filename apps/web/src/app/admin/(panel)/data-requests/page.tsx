"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Req = {
  id: number;
  type: "access" | "deletion";
  status: "pending" | "completed" | "rejected";
  reason: string | null;
  student: string | null;
  requested_at: string | null;
  processed_at: string | null;
};

export default function DataRequestsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "data-requests"],
    queryFn: () => apiJson<{ data: Req[] }>("/api/v1/admin/data-requests"),
  });
  const requests = data?.data ?? [];
  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "data-requests"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const act = useMutation({
    mutationFn: ({ id, verb, reason }: { id: number; verb: "process" | "reject"; reason?: string }) =>
      apiJson(`/api/v1/admin/data-requests/${id}/${verb}`, {
        method: "POST",
        body: JSON.stringify({ reason: reason ?? null }),
      }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">DPDP compliance</p>
      <h1 className="display mt-2 text-3xl text-ink">Data requests</h1>
      <p className="mt-1 text-sm text-muted">
        Access requests compile the student&apos;s data for download. Deletion requests anonymise their PII
        while retaining legally-required financial records. Every action is audit-logged.
      </p>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}

      {isLoading ? (
        <div className="mt-6 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : requests.length === 0 ? (
        <div className="mt-6"><EmptyState title="No requests" body="Student access and deletion requests appear here." /></div>
      ) : (
        <div className="mt-6 space-y-2">
          {requests.map((r) => (
            <div key={r.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">
                    {r.student ?? "Student"} · <span className="uppercase">{r.type}</span>
                  </p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">
                    {r.requested_at ? new Date(r.requested_at).toLocaleDateString("en-IN") : "—"}
                    {r.reason && ` · ${r.reason}`}
                  </p>
                </div>
                {r.status === "pending" ? (
                  <div className="flex gap-2">
                    <button
                      onClick={() => act.mutate({ id: r.id, verb: "process" })}
                      disabled={act.isPending}
                      className={`rounded-full px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50 ${r.type === "deletion" ? "bg-warn" : "bg-trust"}`}
                    >
                      {r.type === "deletion" ? "Anonymise" : "Compile export"}
                    </button>
                    <button
                      onClick={() => act.mutate({ id: r.id, verb: "reject", reason: "Reviewed by grievance officer." })}
                      disabled={act.isPending}
                      className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-warn disabled:opacity-50"
                    >
                      Reject
                    </button>
                  </div>
                ) : (
                  <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${r.status === "completed" ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                    {r.status}
                  </span>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
