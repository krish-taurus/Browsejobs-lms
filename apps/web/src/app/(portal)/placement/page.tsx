"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type Check = { ok: boolean; value: number | null; required: number | null; label: string };
type Pool = { eligible: boolean; checks: Record<string, Check> };

type Job = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  work_mode: string | null;
  salary_range: string | null;
  description: string;
  closes_on: string | null;
  applied: boolean;
};

type Round = { round_no: number; round_type: string | null; happened_on: string; outcome: string; shared: boolean };

type Application = {
  id: number;
  posting: { title: string | null; company: string | null; location: string | null };
  status: string;
  placed_at: string | null;
  rounds: Round[];
};

type Payload = { pool: Pool; jobs: Job[]; applications: Application[] };

export default function PlacementPage() {
  const [data, setData] = useState<Payload | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<number | null>(null);
  const [openJob, setOpenJob] = useState<number | null>(null);
  const [debriefFor, setDebriefFor] = useState<number | null>(null);
  const [debrief, setDebrief] = useState({ round_type: "technical", outcome: "pending", text: "", share: true });
  const [consent, setConsent] = useState(true);
  const [anonymous, setAnonymous] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: Payload }>("/api/v1/me/placement")
      .then((r) => setData(r.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function apply(job: Job) {
    setError(null);
    setNotice(null);
    setBusy(job.id);
    try {
      await apiJson("/api/v1/me/applications", {
        method: "POST",
        body: JSON.stringify({
          job_posting_id: job.id,
          celebrate_consent: consent,
          celebrate_anonymous: anonymous,
        }),
      });
      setNotice(`Applied to ${job.company} — your approved CV went with it. Track it under My applications.`);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not apply.");
    } finally {
      setBusy(null);
    }
  }

  async function submitDebrief(applicationId: number) {
    setError(null);
    try {
      await apiJson(`/api/v1/me/applications/${applicationId}/rounds`, {
        method: "POST",
        body: JSON.stringify({
          round_type: debrief.round_type,
          happened_on: new Date().toISOString().slice(0, 10),
          outcome: debrief.outcome,
          debrief: debrief.text || null,
          share_debrief: debrief.share,
        }),
      });
      setDebriefFor(null);
      setDebrief({ round_type: "technical", outcome: "pending", text: "", share: true });
      setNotice("Round recorded. Shared debriefs make the question bank sharper for everyone — thank you.");
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save the round.");
    }
  }

  if (loading) return <div className="mx-auto max-w-3xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!data) return <div className="mx-auto max-w-3xl text-sm text-muted">Placement isn&apos;t available right now.</div>;

  const checks = Object.values(data.pool.checks);

  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="display text-2xl text-ink">Placement</h1>
      <p className="mt-1 text-sm text-muted">
        Real openings curated by your placement team. Complete the checklist to unlock applications.
      </p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-3 text-sm text-verify">{notice}</p>}

      {/* Pool checklist */}
      <div className="mt-5 rounded-2xl border border-line bg-white p-5">
        <div className="flex items-center justify-between">
          <h2 className="text-xs font-semibold uppercase tracking-widest text-muted">Placement-pool checklist</h2>
          <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${data.pool.eligible ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
            {data.pool.eligible ? "in the pool" : "in progress"}
          </span>
        </div>
        <div className="mt-3 space-y-2">
          {checks.map((c) => (
            <div key={c.label} className="flex items-center justify-between text-sm">
              <span className={c.ok ? "text-ink" : "text-muted"}>
                {c.ok ? "✓" : "○"} {c.label}
              </span>
              {c.required !== null && (
                <span className={`mono text-xs ${c.ok ? "text-verify" : "text-warn"}`}>
                  {c.value ?? "—"} / {c.required}
                </span>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Job board */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Open roles</h2>
      {data.jobs.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No open roles for your course right now — your placement team adds them as they land.</p>
      ) : (
        <div className="mt-3 space-y-2">
          {data.jobs.map((job) => (
            <div key={job.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">{job.title} · {job.company}</p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">
                    {[job.location, job.work_mode, job.salary_range].filter(Boolean).join(" · ")}
                    {job.closes_on && ` · closes ${job.closes_on}`}
                  </p>
                </div>
                {job.applied ? (
                  <span className="mono rounded-full bg-verify-bg px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-verify">applied</span>
                ) : (
                  <button
                    onClick={() => setOpenJob(openJob === job.id ? null : job.id)}
                    className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust"
                  >
                    {openJob === job.id ? "Hide" : "View & apply"}
                  </button>
                )}
              </div>
              {openJob === job.id && (
                <div className="mt-3 border-t border-line pt-3">
                  <p className="whitespace-pre-wrap text-sm text-ink">{job.description}</p>
                  <label className="mt-3 flex items-center gap-2 text-xs text-ink">
                    <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="accent-trust" />
                    If I get this role, you may celebrate it with my batch
                  </label>
                  {consent && (
                    <label className="mt-1 flex items-center gap-2 text-xs text-muted">
                      <input type="checkbox" checked={anonymous} onChange={(e) => setAnonymous(e.target.checked)} className="accent-trust" />
                      …but keep me anonymous
                    </label>
                  )}
                  <button
                    onClick={() => apply(job)}
                    disabled={busy === job.id || !data.pool.eligible}
                    title={data.pool.eligible ? undefined : "Complete the checklist first"}
                    className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                  >
                    {busy === job.id ? "Applying…" : data.pool.eligible ? "Apply with my approved CV" : "Checklist incomplete"}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Applications */}
      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">My applications</h2>
      {data.applications.length === 0 ? (
        <p className="mt-3 text-sm text-muted">Nothing yet — your applications and interview rounds appear here.</p>
      ) : (
        <div className="mt-3 space-y-2">
          {data.applications.map((a) => (
            <div key={a.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">
                    {a.posting.title} · {a.posting.company}
                    {a.status === "placed" && " 🎉"}
                  </p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">{a.status.replace("_", " ")}</p>
                </div>
                {["applied", "shortlisted", "interviewing", "offer"].includes(a.status) && (
                  <button onClick={() => setDebriefFor(debriefFor === a.id ? null : a.id)}
                    className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust">
                    + Record interview round
                  </button>
                )}
              </div>

              {a.rounds.length > 0 && (
                <div className="mt-2 space-y-1">
                  {a.rounds.map((r) => (
                    <p key={r.round_no} className="mono text-[11px] uppercase tracking-widest text-muted">
                      round {r.round_no} · {r.round_type ?? "—"} · {r.outcome}{r.shared && " · shared to bank"}
                    </p>
                  ))}
                </div>
              )}

              {debriefFor === a.id && (
                <div className="mt-3 rounded-[10px] bg-paper p-3">
                  <div className="flex flex-wrap gap-2">
                    <select value={debrief.round_type} onChange={(e) => setDebrief({ ...debrief, round_type: e.target.value })}
                      className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs">
                      {["screening", "technical", "system_design", "hr", "managerial"].map((t) => <option key={t} value={t}>{t.replace("_", " ")}</option>)}
                    </select>
                    <select value={debrief.outcome} onChange={(e) => setDebrief({ ...debrief, outcome: e.target.value })}
                      className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs">
                      {["pending", "advanced", "rejected", "offer"].map((o) => <option key={o} value={o}>{o}</option>)}
                    </select>
                  </div>
                  <textarea value={debrief.text} rows={3}
                    onChange={(e) => setDebrief({ ...debrief, text: e.target.value })}
                    placeholder="What did they ask? Where did you feel strong or stuck? (your debrief sharpens everyone's prep)"
                    className="mt-2 w-full rounded-[8px] border border-line bg-white px-2 py-1.5 text-xs" />
                  <label className="mt-2 flex items-center gap-2 text-xs text-ink">
                    <input type="checkbox" checked={debrief.share} onChange={(e) => setDebrief({ ...debrief, share: e.target.checked })} className="accent-trust" />
                    Share this debrief (anonymised) with the question bank
                  </label>
                  <button onClick={() => submitDebrief(a.id)}
                    className="mt-2 rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white">
                    Save round
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
