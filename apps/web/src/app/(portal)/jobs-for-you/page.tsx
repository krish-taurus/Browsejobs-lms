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
  unlocked: boolean;
};

type PrepQuestion = { question: string; why: string | null; source: string };
type Prep = { unlocked: boolean; questions: PrepQuestion[]; total: number; real_count: number };
type Offer = { product_id: number; sku: string; name: string; price_paise: number };
type Kit = { credits: number; offers: Offer[] };

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
  const [prep, setPrep] = useState<Record<number, Prep>>({});
  const [prepLoading, setPrepLoading] = useState<number | null>(null);
  const [kit, setKit] = useState<Kit>({ credits: 0, offers: [] });

  const load = useCallback(() => {
    apiJson<{ data: Job[]; kit: Kit }>("/api/v1/me/jobs")
      .then((r) => { setJobs(r.data); setKit(r.kit); })
      .catch(() => setJobs([]));
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
      const r = await apiJson<{ data: Prep }>(`/api/v1/me/jobs/${job.id}/prep`);
      setPrep((p) => ({ ...p, [job.id]: r.data }));
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
      if (err instanceof ApiError && err.status === 402) {
        setError("JD mocks are part of the Interview Kit — unlock this job below.");
      } else {
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the mock.");
      }
      setBusy(null);
    }
  }

  async function unlock(job: Job) {
    setBusy(job.id);
    setError(null);
    try {
      await apiJson(`/api/v1/me/jobs/${job.id}/unlock`, { method: "POST" });
      setPrep((p) => { const n = { ...p }; delete n[job.id]; return n; });
      setPrepOpen(null);
      setNotice("Unlocked — the full paper and JD mocks for this job are yours.");
      load();
    } catch (err) {
      if (err instanceof ApiError && err.status === 402) {
        setError("You have no kit credits — buy an Interview Kit below, complete payment in the Store, then unlock.");
      } else {
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not unlock.");
      }
    } finally {
      setBusy(null);
    }
  }

  async function buyKit(offer: Offer) {
    setError(null);
    try {
      await apiJson("/api/v1/me/purchases", { method: "POST", body: JSON.stringify({ product_id: offer.product_id }) });
      setNotice("Order created — complete the payment from the Store page, then hit Unlock on the job.");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the purchase.");
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
                    {prep[job.id].real_count > 0 && ` · ${prep[job.id].real_count} from real interviews for this role`}
                  </p>
                  <ul className="mt-2 space-y-2">
                    {prep[job.id].questions.map((q) => (
                      <li key={q.question} className="text-sm text-ink">
                        {q.question}
                        {q.source === "real" && (
                          <span className="mono ml-2 rounded-full bg-verify-bg px-2 py-0.5 text-[10px] text-verify">asked in a real interview</span>
                        )}
                        {q.why && <span className="block text-xs text-muted">{q.why}</span>}
                      </li>
                    ))}
                  </ul>

                  {!prep[job.id].unlocked && (
                    <div className="mt-4 rounded-[10px] border border-line bg-white p-4">
                      <p className="text-sm font-semibold text-ink">
                        {prep[job.id].total - prep[job.id].questions.length} more question{prep[job.id].total - prep[job.id].questions.length === 1 ? "" : "s"} in the full paper for this job.
                      </p>
                      <p className="mt-1 text-xs text-muted">
                        The Interview Kit unlocks the complete paper, unlimited AI mocks on this exact JD,
                        and includes your JD-rebuilt CV. One job, one kit — prep like you already work there.
                      </p>
                      <div className="mt-3 flex flex-wrap items-center gap-2">
                        {kit.credits > 0 ? (
                          <button onClick={() => unlock(job)} disabled={busy === job.id}
                            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            Unlock with 1 kit credit ({kit.credits} left)
                          </button>
                        ) : (
                          kit.offers.map((o) => (
                            <button key={o.sku} onClick={() => buyKit(o)}
                              className="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-trust hover:border-trust">
                              {o.sku === "job-kit-mentor" ? "Kit + 1:1 mentor" : "Interview Kit"} · ₹{Math.round(o.price_paise / 100)}
                            </button>
                          ))
                        )}
                      </div>
                      {kit.offers.some((o) => o.sku === "job-kit-mentor") && kit.credits === 0 && (
                        <p className="mt-2 text-[11px] text-muted">
                          The ₹{Math.round((kit.offers.find((o) => o.sku === "job-kit-mentor")?.price_paise ?? 29900) / 100)} option adds a
                          live 1:1 with a mentor who preps you for this exact interview.
                        </p>
                      )}
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
