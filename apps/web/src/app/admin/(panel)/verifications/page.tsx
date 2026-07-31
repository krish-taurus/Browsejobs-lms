"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

/**
 * The verification review queue (PRD-E F9).
 *
 * The shipped provider never self-verifies, so nothing a candidate submits
 * settles without someone working this page. Rejections require a reason,
 * because a candidate who is told only "failed" resubmits the same thing.
 */

type CheckRow = {
  id: number;
  kind: string;
  kind_label: string;
  status: string;
  status_label: string;
  provider: string | null;
  provider_ref: string | null;
  evidence: Record<string, string> | null;
  failure_reason: string | null;
  submitted_at: string | null;
  waiting_days: number | null;
  candidate: { id: number | null; name: string | null; email: string | null };
};

type Payload = {
  data: CheckRow[];
  meta: { total: number; current_page: number; last_page: number; pending: number };
};

const STATUS_STYLE: Record<string, string> = {
  pending: "bg-[#fdf1e3] text-[#b8791a]",
  verified: "bg-green-bg text-verify",
  failed: "bg-[#fdeaea] text-warn",
  expired: "bg-paper text-muted",
  not_started: "bg-paper text-muted",
};

export default function AdminVerificationsPage() {
  const qc = useQueryClient();
  const [filter, setFilter] = useState<"open" | "verified" | "failed">("open");
  const [rejecting, setRejecting] = useState<number | null>(null);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "verifications", filter],
    queryFn: () =>
      apiJson<Payload>(
        `/api/v1/admin/verifications${filter === "open" ? "" : `?status=${filter}`}`,
      ),
  });

  const settle = useMutation({
    mutationFn: (vars: { id: number; decision: "verified" | "failed"; reason?: string }) =>
      apiJson(`/api/v1/admin/verifications/${vars.id}/settle`, {
        method: "POST",
        body: JSON.stringify({ decision: vars.decision, reason: vars.reason }),
      }),
    onSuccess: () => {
      setRejecting(null);
      setReason("");
      setError(null);
      qc.invalidateQueries({ queryKey: ["admin", "verifications"] });
    },
    onError: (err) =>
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong."),
  });

  const rows = data?.data ?? [];

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="mono text-[11px] uppercase tracking-widest text-trust">Trust &amp; safety</p>
          <h1 className="display mt-1 text-2xl text-ink">Verification queue</h1>
          <p className="mt-1 max-w-2xl text-sm text-muted">
            A verified badge is a claim BrowseJobs makes to an employer on a candidate&apos;s behalf,
            so every decision here is written to the audit log with your name on it.
          </p>
        </div>
        {data && (
          <span className="mono rounded-full bg-sky px-3 py-1 text-[11px] uppercase tracking-widest text-deep">
            {data.meta.pending} waiting
          </span>
        )}
      </div>

      <div className="flex gap-2">
        {(["open", "verified", "failed"] as const).map((f) => (
          <button
            key={f}
            onClick={() => setFilter(f)}
            aria-pressed={filter === f}
            className={`rounded-full px-4 py-1.5 text-xs font-semibold transition-colors ${
              filter === f ? "bg-trust text-white" : "border border-line bg-white text-ink"
            }`}
          >
            {f === "open" ? "Needs a decision" : f === "verified" ? "Verified" : "Failed"}
          </button>
        ))}
      </div>

      {error && <p className="text-sm text-warn">{error}</p>}

      <div className="rounded-panel border border-line bg-white">
        {isLoading ? (
          <div className="space-y-2 p-5">
            {[0, 1, 2].map((i) => (
              <div key={i} className="shimmer h-16 rounded-lg" />
            ))}
          </div>
        ) : rows.length === 0 ? (
          <EmptyState
            title={filter === "open" ? "Nothing waiting" : "Nothing here"}
            body={
              filter === "open"
                ? "Every submitted check has been decided. Candidates are not waiting on us."
                : "No checks in this state yet."
            }
          />
        ) : (
          <ul className="divide-y divide-line">
            {rows.map((row) => (
              <li key={row.id} className="px-5 py-4">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                  <div className="min-w-[180px] flex-1">
                    <p className="text-sm font-medium text-ink">
                      {row.candidate.name ?? "Candidate"}
                    </p>
                    <p className="mono text-[11px] text-muted">{row.candidate.email}</p>
                  </div>

                  <div className="min-w-[150px]">
                    <p className="text-sm text-ink">{row.kind_label}</p>
                    {row.provider_ref && (
                      <p className="mono text-[11px] text-muted">ref {row.provider_ref}</p>
                    )}
                  </div>

                  <span
                    className={`mono rounded-full px-3 py-1 text-[10px] uppercase tracking-widest ${
                      STATUS_STYLE[row.status] ?? "bg-paper text-muted"
                    }`}
                  >
                    {row.status_label}
                  </span>

                  {/* How long a person has been waiting on us is the only
                      urgency this queue has, so it is stated plainly. */}
                  {row.status === "pending" && row.waiting_days !== null && (
                    <span
                      className={`mono text-[11px] ${row.waiting_days >= 3 ? "text-warn" : "text-muted"}`}
                    >
                      {row.waiting_days === 0 ? "today" : `${row.waiting_days}d waiting`}
                    </span>
                  )}

                  {row.status === "pending" && (
                    <div className="flex gap-2">
                      <button
                        onClick={() => settle.mutate({ id: row.id, decision: "verified" })}
                        disabled={settle.isPending}
                        className="rounded-full bg-verify px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                      >
                        Verify
                      </button>
                      <button
                        onClick={() => {
                          setRejecting(rejecting === row.id ? null : row.id);
                          setReason("");
                        }}
                        className="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-ink"
                      >
                        Reject
                      </button>
                    </div>
                  )}
                </div>

                {row.failure_reason && row.status === "failed" && (
                  <p className="mt-2 text-xs text-muted">Told the candidate: {row.failure_reason}</p>
                )}

                {rejecting === row.id && (
                  <div className="mt-3 rounded-input border border-line bg-paper p-3">
                    <label
                      htmlFor={`reason-${row.id}`}
                      className="block text-xs font-medium text-ink"
                    >
                      What should they fix? This is sent to them word for word.
                    </label>
                    <div className="mt-2 flex flex-wrap gap-2">
                      <input
                        id={`reason-${row.id}`}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="The uploaded ID was unreadable — try a clearer scan."
                        className="min-w-[240px] flex-1 rounded-input border border-line px-3 py-2 text-sm"
                      />
                      <button
                        onClick={() =>
                          settle.mutate({ id: row.id, decision: "failed", reason })
                        }
                        disabled={settle.isPending || reason.trim().length === 0}
                        className="rounded-full bg-warn px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                      >
                        Send rejection
                      </button>
                    </div>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
