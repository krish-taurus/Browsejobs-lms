"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Alert = { id: number; student: string | null; phone: string | null; signals: string[]; created_at: string | null };
type Detractor = { id: number; student: string | null; phone: string | null; milestone: string; score: number; comment: string | null; responded_at: string | null };
type Pause = { id: number; student: string | null; status: string; reason: string; paused_until: string | null };
type Checklist = { id: number; student: string | null; phone: string | null; welcome_call_at: string | null; platform_tour_at: string | null; week1_checkin_at: string | null };

type Payload = {
  alerts: Alert[];
  detractors: Detractor[];
  nps: { responses: number; promoters: number; detractors: number };
  pauses: Pause[];
  onboarding: Checklist[];
};

export default function AdminCarePage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [resolving, setResolving] = useState<{ id: number; text: string } | null>(null);
  const [deciding, setDeciding] = useState<{ id: number; until: string } | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "care"],
    queryFn: () => apiJson<{ data: Payload }>("/api/v1/admin/care"),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "care"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const handleAlert = useMutation({
    mutationFn: ({ id, resolution }: { id: number; resolution: string }) =>
      apiJson(`/api/v1/admin/care/alerts/${id}/handle`, { method: "POST", body: JSON.stringify({ resolution }) }),
    onSuccess: () => { setResolving(null); refresh(); },
    onError,
  });

  const decidePause = useMutation({
    mutationFn: ({ id, decision, until }: { id: number; decision: string; until?: string }) =>
      apiJson(`/api/v1/admin/care/pauses/${id}/decide`, {
        method: "POST",
        body: JSON.stringify({ decision, paused_until: until || null }),
      }),
    onSuccess: () => { setDeciding(null); refresh(); },
    onError,
  });

  const tickOnboarding = useMutation({
    mutationFn: ({ id, step }: { id: number; step: string }) =>
      apiJson(`/api/v1/admin/care/onboarding/${id}`, { method: "PATCH", body: JSON.stringify({ step }) }),
    onSuccess: refresh,
    onError,
  });

  const d = data?.data;
  const nps = d && d.nps.responses > 0
    ? Math.round(((d.nps.promoters - d.nps.detractors) / d.nps.responses) * 100)
    : null;

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Student care</p>
      <h1 className="display mt-2 text-3xl text-ink">Care desk</h1>
      {d && (
        <p className="mono mt-1 text-xs text-muted">
          {nps !== null ? `NPS ${nps}` : "NPS —"} · {d.nps.responses} responses · {d.alerts.length} open alert{d.alerts.length === 1 ? "" : "s"}
        </p>
      )}
      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      {/* Intervention alerts */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">
        Priority interventions{(d?.alerts.length ?? 0) > 0 && ` · ${d!.alerts.length}`}
      </h2>
      {isLoading ? (
        <div className="mt-3 space-y-2">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}</div>
      ) : (d?.alerts.length ?? 0) === 0 ? (
        <p className="mt-3 text-sm text-muted">No open alerts — the signals sweep runs every morning.</p>
      ) : (
        <div className="mt-3 space-y-2">
          {d!.alerts.map((a) => (
            <div key={a.id} className="rounded-[14px] border border-warn/40 bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">{a.student} <span className="mono text-xs text-muted">{a.phone}</span></p>
                  <p className="mono text-[11px] uppercase tracking-widest text-warn">{a.signals.join(" · ")}</p>
                </div>
                <button onClick={() => setResolving({ id: a.id, text: "" })} className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white">
                  Mark handled
                </button>
              </div>
              {resolving?.id === a.id && (
                <div className="mt-2 flex gap-2">
                  <input value={resolving.text} onChange={(e) => setResolving({ ...resolving, text: e.target.value })}
                    placeholder="What happened on the call?"
                    className="flex-1 rounded-[10px] border border-line bg-white px-3 py-1.5 text-xs" />
                  <button onClick={() => handleAlert.mutate({ id: a.id, resolution: resolving.text })}
                    disabled={!resolving.text.trim()}
                    className="rounded-full border border-line px-3 py-1 text-xs font-semibold disabled:opacity-50">Save</button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Detractors */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Detractor rescues</h2>
      {(d?.detractors.length ?? 0) === 0 ? (
        <p className="mt-3 text-sm text-muted">No detractor responses.</p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {d!.detractors.map((x) => (
            <div key={x.id} className="px-5 py-3">
              <p className="text-sm text-ink">
                {x.student} <span className="mono text-xs text-warn">scored {x.score}</span>
                <span className="mono ml-2 text-[10px] uppercase tracking-widest text-muted">{x.milestone} · {x.phone}</span>
              </p>
              {x.comment && <p className="mt-1 text-xs text-muted">&ldquo;{x.comment}&rdquo;</p>}
            </div>
          ))}
        </div>
      )}

      {/* Pause requests */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Pause requests</h2>
      {(d?.pauses.length ?? 0) === 0 ? (
        <p className="mt-3 text-sm text-muted">No pause requests.</p>
      ) : (
        <div className="mt-3 space-y-2">
          {d!.pauses.map((p) => (
            <div key={p.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">{p.student}</p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">
                    {p.status}{p.paused_until && ` until ${p.paused_until}`}
                  </p>
                  <p className="mt-1 text-xs text-muted">{p.reason}</p>
                </div>
                {p.status === "requested" && (
                  <div className="flex items-center gap-2">
                    {deciding?.id === p.id ? (
                      <>
                        <input type="date" value={deciding.until} onChange={(e) => setDeciding({ ...deciding, until: e.target.value })}
                          className="rounded-[8px] border border-line px-2 py-1 text-xs" />
                        <button onClick={() => decidePause.mutate({ id: p.id, decision: "approved", until: deciding.until })}
                          disabled={!deciding.until}
                          className="rounded-full bg-trust px-3 py-1 text-xs font-semibold text-white disabled:opacity-50">Approve</button>
                      </>
                    ) : (
                      <>
                        <button onClick={() => setDeciding({ id: p.id, until: "" })}
                          className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white">Approve…</button>
                        <button onClick={() => decidePause.mutate({ id: p.id, decision: "rejected" })}
                          className="text-xs text-warn hover:underline">Reject</button>
                      </>
                    )}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Onboarding */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Week-1 onboarding</h2>
      {(d?.onboarding.length ?? 0) === 0 ? (
        <div className="mt-3"><EmptyState title="All onboarded" body="Fresh enrolments appear here for the welcome call, tour, and week-1 check-in." /></div>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {d!.onboarding.map((c) => (
            <div key={c.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{c.student} <span className="mono text-xs text-muted">{c.phone}</span></p>
              </div>
              {([["welcome_call", c.welcome_call_at, "Call"], ["platform_tour", c.platform_tour_at, "Tour"], ["week1_checkin", c.week1_checkin_at, "Check-in"]] as const).map(([step, done, label]) => (
                <button key={step} onClick={() => !done && tickOnboarding.mutate({ id: c.id, step })}
                  disabled={!!done}
                  className={`rounded-full px-3 py-1 text-[11px] font-semibold ${done ? "bg-verify-bg text-verify" : "border border-line bg-white text-ink hover:border-trust"}`}>
                  {done ? `✓ ${label}` : label}
                </button>
              ))}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
