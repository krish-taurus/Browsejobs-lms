"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Round = { round_no: number; round_type: string | null; outcome: string; debrief: string | null; shared: boolean };

type AppRow = {
  id: number;
  student: string | null;
  posting: string;
  status: string;
  note: string | null;
  offer_ctc_paise: number | null;
  offer_role: string | null;
  placed_at: string | null;
  celebrate_consent: boolean;
  rounds: Round[];
};

type JobRow = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  status: string;
  applications_count: number;
  closes_on: string | null;
};

type Payload = {
  pipeline: string[];
  board: Record<string, AppRow[]>;
  jobs: JobRow[];
  stats: { placed: number; offers: number; active: number };
};

export default function AdminPlacementsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [job, setJob] = useState({ title: "", company: "", description: "", location: "", salary_range: "" });
  const [offer, setOffer] = useState<{ id: number; ctc: string; role: string } | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "placements"],
    queryFn: () => apiJson<{ data: Payload }>("/api/v1/admin/placements"),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "placements"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const createJob = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/jobs", {
        method: "POST",
        body: JSON.stringify({
          title: job.title, company: job.company, description: job.description,
          location: job.location || null, salary_range: job.salary_range || null,
        }),
      }),
    onSuccess: () => { setJob({ title: "", company: "", description: "", location: "", salary_range: "" }); setError(null); refresh(); },
    onError,
  });

  const move = useMutation({
    mutationFn: ({ id, status, ctc, role }: { id: number; status: string; ctc?: string; role?: string }) =>
      apiJson(`/api/v1/admin/applications/${id}`, {
        method: "PATCH",
        body: JSON.stringify({
          status,
          offer_ctc_paise: ctc ? Math.round(Number(ctc) * 100_000) * 100 : undefined,
          offer_role: role || undefined,
        }),
      }),
    onSuccess: () => { setOffer(null); setError(null); refresh(); },
    onError,
  });

  const payload = data?.data;
  const stages = ["applied", "shortlisted", "interviewing", "offer", "placed"];

  return (
    <div className="mx-auto max-w-5xl">
      <p className="kicker text-trust">Placement</p>
      <h1 className="display mt-2 text-3xl text-ink">Pipeline</h1>
      {payload && (
        <p className="mono mt-1 text-xs text-muted">
          {payload.stats.active} active · {payload.stats.offers} offers · {payload.stats.placed} placed
        </p>
      )}
      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      {/* New job */}
      <div className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <h2 className="text-sm font-semibold text-ink">Post an opening</h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
          <input value={job.title} onChange={(e) => setJob({ ...job, title: e.target.value })} placeholder="Role title"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={job.company} onChange={(e) => setJob({ ...job, company: e.target.value })} placeholder="Company"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={job.location} onChange={(e) => setJob({ ...job, location: e.target.value })} placeholder="Location (optional)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={job.salary_range} onChange={(e) => setJob({ ...job, salary_range: e.target.value })} placeholder="Salary range (optional)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
        </div>
        <textarea value={job.description} onChange={(e) => setJob({ ...job, description: e.target.value })} rows={3}
          placeholder="Description…" className="mt-3 w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
        <button onClick={() => createJob.mutate()} disabled={createJob.isPending || !job.title || !job.company || !job.description}
          className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {createJob.isPending ? "Posting…" : "Post opening"}
        </button>
      </div>

      {/* Board */}
      {isLoading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-20 rounded-[14px]" />)}</div>
      ) : (
        <div className="mt-8 grid gap-3 lg:grid-cols-5">
          {stages.map((stage) => (
            <div key={stage} className="rounded-[14px] border border-line bg-paper/60 p-3">
              <p className="mono text-[11px] font-semibold uppercase tracking-widest text-muted">
                {stage} · {(payload?.board[stage] ?? []).length}
              </p>
              <div className="mt-2 space-y-2">
                {(payload?.board[stage] ?? []).map((a) => (
                  <div key={a.id} className="rounded-[10px] border border-line bg-white p-3">
                    <p className="text-xs font-semibold text-ink">{a.student}</p>
                    <p className="text-[11px] text-muted">{a.posting}</p>
                    {a.rounds.length > 0 && (
                      <p className="mono mt-1 text-[10px] uppercase tracking-widest text-muted">
                        {a.rounds.length} round{a.rounds.length === 1 ? "" : "s"}
                      </p>
                    )}
                    {a.status === "placed" ? (
                      <p className="mono mt-1 text-[10px] uppercase tracking-widest text-verify">
                        placed{a.celebrate_consent ? " · celebration drafted" : ""}
                      </p>
                    ) : offer?.id === a.id ? (
                      <div className="mt-2 space-y-1.5">
                        <input value={offer.role} onChange={(e) => setOffer({ ...offer, role: e.target.value })}
                          placeholder="Offer role" className="w-full rounded-[8px] border border-line px-2 py-1 text-[11px]" />
                        <input value={offer.ctc} onChange={(e) => setOffer({ ...offer, ctc: e.target.value })}
                          placeholder="CTC (LPA)" className="w-full rounded-[8px] border border-line px-2 py-1 text-[11px]" />
                        <div className="flex gap-2">
                          <button onClick={() => move.mutate({ id: a.id, status: "placed", ctc: offer.ctc, role: offer.role })}
                            className="rounded-full bg-trust px-3 py-1 text-[11px] font-semibold text-white">Mark placed</button>
                          <button onClick={() => setOffer(null)} className="text-[11px] text-muted">Cancel</button>
                        </div>
                      </div>
                    ) : (
                      <div className="mt-2 flex flex-wrap gap-1.5">
                        {stages.filter((s) => s !== stage && s !== "placed").map((s) => (
                          <button key={s} onClick={() => move.mutate({ id: a.id, status: s })}
                            className="rounded-full border border-line bg-white px-2 py-0.5 text-[10px] text-ink hover:border-trust">
                            → {s}
                          </button>
                        ))}
                        <button onClick={() => setOffer({ id: a.id, ctc: "", role: "" })}
                          className="rounded-full bg-verify-bg px-2 py-0.5 text-[10px] font-semibold text-verify">
                          → placed
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Jobs */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Openings</h2>
      {(payload?.jobs ?? []).length === 0 ? (
        <div className="mt-3"><EmptyState title="No openings yet" body="Post real roles above — students in the pool apply with their approved CV." /></div>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {payload!.jobs.map((j) => (
            <div key={j.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{j.title} · {j.company}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">
                  {j.location ?? "—"} · {j.applications_count} application{j.applications_count === 1 ? "" : "s"}
                </p>
              </div>
              <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${j.status === "open" ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                {j.status}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
