"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";
import { candidateApi, cvMockApi, type InternalJob, type MyMockStatus } from "@/lib/candidate";
import { mockPath } from "@/lib/mockKinds";
import { JobCard } from "@/components/jobs/JobCard";
import { JobIcon } from "@/components/jobs/JobIcons";
import { CareerGuidanceCard, UpdateCVCard } from "@/components/jobs/JobsRail";
import { FOCUS_RING, ReadinessBanner } from "@/components/jobs/ReadinessBanner";

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
type CompletenessItem = { key: string; label: string; done: boolean };

/**
 * Only the facts a CV can actually carry are worth nagging about — "at
 * least one graded mock" is a real signal but fixing it here would send
 * someone to the wrong page.
 */
const CV_FIXABLE = new Set(["resume", "experience", "education", "summary"]);

function matchTone(pct: number): string {
  if (pct >= 70) return "bg-verify-bg text-verify";
  if (pct >= 40) return "bg-sky text-deep";
  return "bg-paper text-muted";
}

/** Wider Market results per page. */
const PAGE_SIZE = 10;

const GHOST = "min-h-[44px] rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50";

export default function JobsForYouPage() {
  const router = useRouter();
  const [jobs, setJobs] = useState<Job[] | null>(null);
  // Whether the candidate has a CV at all — separate from `jobs` being
  // empty, since "no CV yet" and "no matches for your CV" need different
  // messages: one points at My CV, the other just says keep building skills.
  const [hasCv, setHasCv] = useState<boolean | null>(null);
  const [page, setPage] = useState(0);
  // Roles employers publish directly on BrowseJobs (PRD-E F3) — applying is
  // free and instant, but the JD's own AI interview comes FIRST: it's a
  // precondition of Apply (ApplyToEmployerJob rejects otherwise), not an
  // optional follow-up. Kept in its own state and its own section rather
  // than merged into the external `jobs` list, because the two apply flows
  // are genuinely different actions.
  const [employerJobs, setEmployerJobs] = useState<InternalJob[] | null>(null);
  const [jobBusy, setJobBusy] = useState<number | null>(null);
  const [jobError, setJobError] = useState<{ id: number; message: string } | null>(null);
  const [completeness, setCompleteness] = useState<CompletenessItem[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [prepOpen, setPrepOpen] = useState<number | null>(null);
  const [prep, setPrep] = useState<Record<number, Prep>>({});
  const [prepLoading, setPrepLoading] = useState<number | null>(null);
  const [kit, setKit] = useState<Kit>({ credits: 0, offers: [] });
  const [cvMock, setCvMock] = useState<MyMockStatus | null>(null);
  const [cvMockBusy, setCvMockBusy] = useState(false);

  const load = useCallback(() => {
    apiJson<{ data: Job[]; kit: Kit; has_cv: boolean }>("/api/v1/me/jobs")
      .then((r) => { setJobs(r.data); setKit(r.kit); setHasCv(r.has_cv); setPage(0); })
      .catch(() => setJobs([]));
  }, []);
  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    candidateApi
      .board()
      .then((r) => setEmployerJobs(r.data.internal))
      .catch(() => setEmployerJobs([]));
  }, []);

  useEffect(() => {
    cvMockApi
      .status()
      .then((r) => setCvMock(r.data))
      .catch(() => setCvMock({ status: "none", mock_id: null, score: null, attempts: { used: 0, limit: 2 } }));
  }, []);

  // A thin CV is not a bug in generation — it is an empty My CV profile.
  // Surfaced here (and again right before Apply) rather than blocked,
  // because applying free and improving the CV later both stay available.
  useEffect(() => {
    candidateApi
      .dashboard()
      .then((r) => setCompleteness(r.data.profile_completeness.items))
      .catch(() => {});
  }, []);

  /** The AI Readiness interview — starts or resumes the student's own CV interview. */
  async function startCvMock() {
    if (cvMockBusy) return;
    setCvMockBusy(true);
    setError(null);
    try {
      const r = await cvMockApi.start();
      router.push(mockPath("cv", r.data.mock_id, true));
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the interview.");
      setCvMockBusy(false);
    }
  }

  /** A direct-hiring role's own interview — the server resumes one already in progress. */
  async function startJobInterview(job: InternalJob) {
    if (jobBusy !== null) return;
    setJobBusy(job.id);
    setJobError(null);
    try {
      const r = await candidateApi.startMock(job.id);
      router.push(mockPath("job", r.data.mock_id, true));
    } catch (err) {
      setJobError({ id: job.id, message: err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the interview." });
      setJobBusy(null);
    }
  }

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
      const r = await apiJson<{ data: { mock_id: number } }>(`/api/v1/me/jobs/${job.id}/mock`, { method: "POST" });
      router.push(mockPath("job", r.data.mock_id));
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

  if (!jobs) {
    return (
      <div className="mx-auto max-w-[1240px]" aria-busy="true">
        <div className="shimmer h-20 rounded-[18px]" />
        <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
          <div className="shimmer h-56 rounded-[18px]" />
          <div className="shimmer h-56 rounded-[18px]" />
        </div>
      </div>
    );
  }

  const missingCv = (completeness ?? [])
    .filter((i) => CV_FIXABLE.has(i.key) && !i.done)
    .map((i) => i.label.toLowerCase());

  return (
    <div className="mx-auto max-w-[1240px]">
      <header>
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-trust">Your next career move</p>
        <h1 className="display mt-2 text-[32px] leading-[1.1] text-ink sm:text-[40px]">Jobs for You</h1>
        <p className="mt-2 text-base text-muted">Discover roles that fit your skills. Show employers what you can do.</p>
      </header>

      {error && <p role="alert" className="mt-4 text-sm text-warn">{error}</p>}
      {notice && <p role="status" className="mt-4 text-sm text-verify">{notice}</p>}

      <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div className="min-w-0">
          <ReadinessBanner status={cvMock} busy={cvMockBusy} onStart={startCvMock} />

          {employerJobs === null ? (
            <div className="mt-8 shimmer h-48 rounded-[18px]" />
          ) : employerJobs.length > 0 ? (
            <section aria-labelledby="direct-title" className="mt-8">
              <div className="flex flex-wrap items-center gap-3">
                <h2 id="direct-title" className="display text-[22px] text-ink">Hiring directly on BrowseJobs</h2>
                <span className="rounded-full border border-trust/30 bg-sky px-2.5 py-1 text-xs font-semibold text-trust">Direct hiring</span>
              </div>
              <p className="mt-1 text-[15px] text-muted">Complete a role-specific AI interview to unlock Apply.</p>
              <div className="mt-4 space-y-4">
                {employerJobs.map((job) => (
                  <JobCard
                    key={job.id}
                    job={job}
                    busy={jobBusy === job.id}
                    error={jobError?.id === job.id ? jobError.message : null}
                    onStartInterview={startJobInterview}
                  />
                ))}
              </div>
            </section>
          ) : null}

          <section aria-labelledby="market-title" className="mt-10">
            <h2 id="market-title" className="display text-[22px] text-ink">Wider market</h2>
            <p className="mt-1 text-[15px] text-muted">
              Live openings ranked by how well they fit your skills. Match shows what you already have;
              confidence blends your course progress and mock performance into your odds for that interview.
            </p>

            {hasCv === false ? (
              <div className="mt-4 rounded-[18px] border border-line bg-white p-8 text-center shadow-soft">
                <p className="text-sm font-semibold text-ink">Build your CV to see matched roles here</p>
                <p className="mt-1 text-sm text-muted">
                  Every match here is &quot;how well does your CV fit this JD&quot; — there&apos;s nothing to match against yet.
                </p>
                <a href="/cv" className={`mt-3 inline-flex min-h-[44px] items-center rounded-full bg-trust px-5 text-sm font-semibold text-white hover:bg-deep ${FOCUS_RING}`}>
                  Build my CV →
                </a>
              </div>
            ) : jobs.length === 0 ? (
              <div className="mt-4 rounded-[18px] border border-line bg-white p-8 text-center shadow-soft">
                <p className="text-sm text-muted">No matched roles right now. Complete more topics and mocks — your feed sharpens as your profile grows.</p>
              </div>
            ) : (
              <div className="mt-4 space-y-4">
                {jobs.slice(page * PAGE_SIZE, page * PAGE_SIZE + PAGE_SIZE).map((job) => (
                  <article key={job.id} className="rounded-[18px] border border-line bg-white p-5 shadow-soft sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h3 className="text-base font-semibold text-ink">
                          {job.title} · {job.company}
                          {job.saved && <span className="mono ml-2 text-[10px] uppercase tracking-widest text-trust">saved</span>}
                        </h3>
                        <p className="mono mt-0.5 text-[11px] uppercase tracking-widest text-muted">
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
                      <p className="mt-2 text-[12px] text-muted">
                        Confidence is based on {job.confidence_based_on.join(" + ")} — take a quick mock below to firm it up.
                      </p>
                    )}

                    {(job.matched.length > 0 || job.gap.length > 0) && (
                      <div className="mt-3 flex flex-wrap gap-1.5">
                        {job.matched.slice(0, 8).map((s) => (
                          <span key={`m-${s}`} className="inline-flex items-center gap-1 rounded-full bg-verify-bg px-2.5 py-1 text-[12px] text-verify">
                            <JobIcon name="check" className="h-3.5 w-3.5" /> {s}
                          </span>
                        ))}
                        {job.gap.slice(0, 6).map((s) => (
                          <span key={`g-${s}`} className="rounded-full bg-paper px-2.5 py-1 text-[12px] text-muted">gap: {s}</span>
                        ))}
                      </div>
                    )}

                    <div className="mt-4 flex flex-wrap items-center gap-2">
                      <button onClick={() => apply(job)} disabled={busy === job.id}
                        className={`min-h-[44px] rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50 ${FOCUS_RING}`}>
                        {busy === job.id ? "Preparing…" : "Apply with tailored CV"}
                      </button>
                      <button onClick={() => togglePrep(job)} disabled={prepLoading === job.id} className={`${GHOST} ${FOCUS_RING}`}>
                        {prepLoading === job.id ? "Loading…" : prepOpen === job.id ? "Hide questions" : "Likely questions"}
                      </button>
                      <button onClick={() => quickMock(job)} disabled={busy === job.id} className={`${GHOST} ${FOCUS_RING}`}>
                        Quick mock
                      </button>
                      {job.apply_url && (
                        <a href={job.apply_url} target="_blank" rel="noopener noreferrer"
                          className={`inline-flex items-center gap-1.5 ${GHOST} ${FOCUS_RING}`}>
                          View posting <JobIcon name="externalLink" className="h-4 w-4" />
                        </a>
                      )}
                      <button onClick={() => act(job.id, "save")} disabled={busy === job.id} className={`${GHOST} ${FOCUS_RING}`}>
                        {job.saved ? "Saved" : "Save"}
                      </button>
                      <button onClick={() => act(job.id, "dismiss")} disabled={busy === job.id}
                        className={`min-h-[44px] rounded-full px-4 py-2 text-sm font-semibold text-muted hover:text-ink disabled:opacity-50 ${FOCUS_RING}`}>
                        Dismiss
                      </button>
                    </div>

                    {prepOpen === job.id && prep[job.id] && (
                      <div className="mt-3 rounded-xl bg-paper p-4">
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
                          <div className="mt-4 rounded-xl border border-line bg-white p-4">
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
                                  className={`min-h-[44px] rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50 ${FOCUS_RING}`}>
                                  Unlock with 1 kit credit ({kit.credits} left)
                                </button>
                              ) : (
                                kit.offers.map((o) => (
                                  <button key={o.sku} onClick={() => buyKit(o)}
                                    className={`min-h-[44px] rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-trust hover:border-trust ${FOCUS_RING}`}>
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
                  </article>
                ))}
              </div>
            )}

            {hasCv !== false && jobs.length > PAGE_SIZE && (
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <span className="text-xs text-muted">
                  Showing {page * PAGE_SIZE + 1}–{Math.min(jobs.length, page * PAGE_SIZE + PAGE_SIZE)} of {jobs.length}
                </span>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => setPage((p) => Math.max(0, p - 1))}
                    disabled={page === 0}
                    className={`${GHOST} disabled:opacity-40 ${FOCUS_RING}`}
                  >
                    ← Prev
                  </button>
                  <span className="mono text-xs text-muted">
                    Page {page + 1} of {Math.ceil(jobs.length / PAGE_SIZE)}
                  </span>
                  <button
                    onClick={() => setPage((p) => Math.min(Math.ceil(jobs.length / PAGE_SIZE) - 1, p + 1))}
                    disabled={page >= Math.ceil(jobs.length / PAGE_SIZE) - 1}
                    className={`${GHOST} disabled:opacity-40 ${FOCUS_RING}`}
                  >
                    Next →
                  </button>
                </div>
              </div>
            )}
          </section>
        </div>

        <aside className="space-y-6" aria-label="Guidance">
          <CareerGuidanceCard />
          <UpdateCVCard missing={missingCv} />
        </aside>
      </div>
    </div>
  );
}
