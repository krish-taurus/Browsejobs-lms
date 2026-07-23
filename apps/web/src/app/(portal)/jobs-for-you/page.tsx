"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";

type Job = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  work_mode: string | null;
  source_kind: string;
  apply_url: string | null;
  posted_at: string | null;
  match_pct: number;
  matched: string[];
  gap: string[];
  saved: boolean;
  confidence_pct: number;
  confidence_based_on: string[];
  has_mock_signal: boolean;
};

type PrepQuestion = { question: string; why: string | null; source: string };

function matchTone(pct: number): string {
  if (pct >= 70) return "bg-verify-bg text-verify";
  if (pct >= 40) return "bg-sky text-deep";
  return "bg-paper text-muted";
}

export default function JobsForYouPage() {
  const router = useRouter();
  const [jobs, setJobs] = useState<Job[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [prepOpen, setPrepOpen] = useState<number | null>(null);
  const [prep, setPrep] = useState<Record<number, PrepQuestion[]>>({});
  const [prepLoading, setPrepLoading] = useState<number | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: Job[] }>("/api/v1/me/jobs").then((r) => setJobs(r.data)).catch(() => setJobs([]));
  }, []);
  useEffect(() => { load(); }, [load]);

  async function act(id: number, verb: "save" | "dismiss") {
    setBusy(id);
    setError(null);
    try {
      await apiJson(`/api/v1/me/jobs/${id}/${verb}`, { method: "POST" });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(null);
    }
  }

  async function apply(job: Job) {
    setBusy(job.id);
    setError(null);
    setNotice(null);
    try {
      const r = await apiJson<{ data: { ats_score: number | null } }>(`/api/v1/me/jobs/${job.id}/apply`, { method: "POST" });
      setNotice(`Tailored CV ready in My CV${r.data.ats_score != null ? ` (ATS ${r.data.ats_score})` : ""}. Opening the posting — your application is tracked under Placement.`);
      if (job.apply_url) window.open(job.apply_url, "_blank", "noopener,noreferrer");
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the application.");
    } finally {
      setBusy(null);
    }
  }

  async function togglePrep(job: Job) {
    if (prepOpen === job.id) {
      setPrepOpen(null);
      return;
    }
    setPrepOpen(job.id);
    if (prep[job.id]) return;
    setPrepLoading(job.id);
    setError(null);
    try {
      const r = await apiJson<{ data: { questions: PrepQuestion[] } }>(`/api/v1/me/jobs/${job.id}/prep`);
      setPrep((p) => ({ ...p, [job.id]: r.data.questions }));
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not load the questions.");
      setPrepOpen(null);
    } finally {
      setPrepLoading(null);
    }
  }

  async function quickMock(job: Job) {
    setBusy(job.id);
    setError(null);
    try {
      await apiJson<{ data: { mock_id: number } }>(`/api/v1/me/jobs/${job.id}/mock`, { method: "POST" });
      router.push("/mock");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the mock.");
      setBusy(null);
    }
  }

  if (!jobs) return <div className="mx-auto max-w-3xl"><div className="shimmer h-64 rounded-[14px]" /></div>;

  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="display text-2xl text-ink">Jobs for You</h1>
      <p className="mt-1 text-sm text-muted">
        Live openings ranked by how well they fit your skills. Match shows what you already have;
        confidence blends your course progress and mock performance into your odds for that interview.
      </p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-3 text-sm text-verify">{notice}</p>}

      {jobs.length === 0 ? (
        <div className="mt-8 rounded-[14px] border border-line bg-white p-8 text-center">
          <p className="text-sm text-muted">No matched roles right now. Complete more topics and mocks — your feed sharpens as your profile grows.</p>
        </div>
      ) : (
        <div className="mt-6 space-y-3">
          {jobs.map((job) => (
            <div key={job.id} className="rounded-[14px] border border-line bg-white p-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="text-sm font-semibold text-ink">
                    {job.title} · {job.company}
                    {job.saved && <span className="mono ml-2 text-[10px] uppercase tracking-widest text-trust">saved</span>}
                  </p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">
                    {[job.location, job.work_mode, job.source_kind, job.posted_at].filter(Boolean).join(" · ")}
                  </p>
                </div>
                <span className="flex shrink-0 flex-col items-end gap-1">
                  <span className={`mono rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest ${matchTone(job.match_pct)}`}>
                    {job.match_pct}% match
                  </span>
                  <span
                    className={`mono rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest ${matchTone(job.confidence_pct)}`}
                    title={`Based on ${job.confidence_based_on.join(" + ")}`}
                  >
                    {job.confidence_pct}% confidence
                  </span>
                </span>
              </div>

              {!job.has_mock_signal && (
                <p className="mt-2 text-[11px] text-muted">
                  Confidence is based on {job.confidence_based_on.join(" + ")} — take a quick mock below to firm it up.
                </p>
              )}

              {(job.matched.length > 0 || job.gap.length > 0) && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {job.matched.slice(0, 8).map((s) => (
                    <span key={`m-${s}`} className="mono rounded-full bg-verify-bg px-2 py-0.5 text-[10px] text-verify">✓ {s}</span>
                  ))}
                  {job.gap.slice(0, 6).map((s) => (
                    <span key={`g-${s}`} className="mono rounded-full bg-paper px-2 py-0.5 text-[10px] text-muted">gap: {s}</span>
                  ))}
                </div>
              )}

              <div className="mt-4 flex flex-wrap items-center gap-2">
                <button onClick={() => apply(job)} disabled={busy === job.id}
                  className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                  {busy === job.id ? "Preparing…" : "Apply with tailored CV"}
                </button>
                <button onClick={() => togglePrep(job)} disabled={prepLoading === job.id}
                  className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50">
                  {prepLoading === job.id ? "Loading…" : prepOpen === job.id ? "Hide questions" : "Likely questions"}
                </button>
                <button onClick={() => quickMock(job)} disabled={busy === job.id}
                  className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50">
                  Quick mock
                </button>
                {job.apply_url && (
                  <a href={job.apply_url} target="_blank" rel="noopener noreferrer"
                    className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust">
                    View posting
                  </a>
                )}
                <button onClick={() => act(job.id, "save")} disabled={busy === job.id}
                  className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50">
                  {job.saved ? "Saved" : "Save"}
                </button>
                <button onClick={() => act(job.id, "dismiss")} disabled={busy === job.id}
                  className="rounded-full px-4 py-2 text-sm font-semibold text-muted hover:text-ink disabled:opacity-50">
                  Dismiss
                </button>
              </div>

              {prepOpen === job.id && prep[job.id] && (
                <div className="mt-3 rounded-[10px] bg-paper p-4">
                  <p className="mono text-[10px] uppercase tracking-widest text-muted">
                    Questions this interview is likely to ask
                  </p>
                  <ul className="mt-2 space-y-2">
                    {prep[job.id].map((q) => (
                      <li key={q.question} className="text-sm text-ink">
                        {q.question}
                        {q.source === "real" && (
                          <span className="mono ml-2 rounded-full bg-verify-bg px-2 py-0.5 text-[10px] text-verify">asked in a real interview</span>
                        )}
                        {q.why && <span className="block text-xs text-muted">{q.why}</span>}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
