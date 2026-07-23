"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Candidate = {
  id: number; name: string; email: string; batches: string[]; rank: number;
  attendance_pct: number; mock_pct: number; mocks_done: number;
  assignment_pct: number; assignments_done: number; assignments_total: number;
  total: number; band: "green" | "amber" | "red";
};
type Detail = {
  candidate: { id: number; name: string; email: string };
  components: Candidate; pri: number | null; engagement: number | null;
  mastery: Record<string, number>; recent_mocks: { id: number; mode: string; score: number | null; completed_at: string | null }[];
};
type Payload = { batches: { id: number; number: string }[]; candidates: Candidate[] };

const BAND = {
  green: "bg-verify-bg text-verify",
  amber: "bg-[#FDF3E1] text-amber",
  red: "bg-[#FBE9E9] text-warn",
};

export default function CandidatePerformancePage() {
  const [batchId, setBatchId] = useState("");
  const [q, setQ] = useState("");
  const [openId, setOpenId] = useState<number | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "performance", batchId, q],
    queryFn: () => apiJson<{ data: Payload }>(
      `/api/v1/admin/candidate-performance?${new URLSearchParams({ ...(batchId && { batch_id: batchId }), ...(q && { q }) })}`,
    ),
  });
  const detail = useQuery({
    queryKey: ["admin", "performance-detail", openId],
    queryFn: () => apiJson<{ data: Detail }>(`/api/v1/admin/candidate-performance/${openId}`),
    enabled: openId !== null,
  });

  const rows = data?.data.candidates ?? [];

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Candidate performance</p>
      <h1 className="display mt-2 text-3xl text-ink">Every candidate, ranked</h1>
      <p className="mt-1 text-sm text-muted">
        Weighted score: attendance 50% · mocks 30% · assignments 20%. Green ≥ 80, amber 60–79, red
        below 60. The same score drives each candidate&apos;s post-module report and the leaderboard.
      </p>

      <div className="mt-5 flex flex-wrap gap-3">
        <select value={batchId} onChange={(e) => setBatchId(e.target.value)} className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust">
          <option value="">All batches</option>
          {data?.data.batches.map((b) => <option key={b.id} value={b.id}>{b.number}</option>)}
        </select>
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name or email…" className="min-w-56 flex-1 rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
      </div>

      {isLoading ? (
        <div className="mt-6 space-y-2">{Array.from({ length: 5 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : rows.length === 0 ? (
        <div className="mt-6"><EmptyState title="No candidates" body="No students match this filter yet." /></div>
      ) : (
        <div className="mt-6 space-y-2">
          {rows.map((c) => (
            <div key={c.id} className="rounded-[14px] border border-line bg-white">
              <button onClick={() => setOpenId(openId === c.id ? null : c.id)} className="flex w-full flex-wrap items-center gap-3 p-4 text-left">
                <span className="mono w-8 text-sm text-muted">#{c.rank}</span>
                <span className={`mono rounded-full px-2.5 py-1 text-xs font-semibold ${BAND[c.band]}`}>{c.total.toFixed(1)}</span>
                <span className="text-sm font-semibold text-ink">{c.name}</span>
                {c.batches.map((b) => <span key={b} className="mono rounded-full bg-paper px-2 py-0.5 text-[10px] text-muted">{b}</span>)}
                <span className="mono ml-auto text-[11px] text-muted">
                  att {c.attendance_pct.toFixed(0)}% · mocks {c.mocks_done} (avg {c.mock_pct.toFixed(0)}) · asgn {c.assignments_done}/{c.assignments_total}
                </span>
              </button>
              {openId === c.id && (
                <div className="border-t border-line px-4 py-3 text-sm">
                  {detail.isLoading || detail.data?.data.candidate.id !== c.id ? (
                    <div className="shimmer h-16 rounded-[10px]" />
                  ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                      <div>
                        <p className="mono text-[10px] uppercase tracking-widest text-muted">Signals</p>
                        <p className="mt-1 text-ink">PRI {detail.data.data.pri ?? "—"} · engagement {detail.data.data.engagement ?? "—"}</p>
                        <p className="mono mt-2 text-[10px] uppercase tracking-widest text-muted">Module mastery</p>
                        {Object.keys(detail.data.data.mastery).length === 0 ? (
                          <p className="mt-1 text-muted">No mastery data yet.</p>
                        ) : (
                          <div className="mt-1 space-y-1">
                            {Object.entries(detail.data.data.mastery).map(([m, v]) => (
                              <p key={m} className="text-ink"><span className="mono text-xs text-muted">{m}:</span> {Math.round(Number(v))}%</p>
                            ))}
                          </div>
                        )}
                      </div>
                      <div>
                        <p className="mono text-[10px] uppercase tracking-widest text-muted">Recent mocks</p>
                        {detail.data.data.recent_mocks.length === 0 ? (
                          <p className="mt-1 text-muted">No completed mocks yet.</p>
                        ) : (
                          <div className="mt-1 space-y-1">
                            {detail.data.data.recent_mocks.map((m) => (
                              <p key={m.id} className="text-ink">
                                <span className="mono text-xs text-muted">{m.completed_at} · {m.mode}:</span> {m.score ?? "—"}/100
                              </p>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
