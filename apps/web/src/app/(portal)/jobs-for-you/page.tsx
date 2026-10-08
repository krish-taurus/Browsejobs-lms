"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";
import { candidateApi, cvMockApi, mockRecordingApi, type InternalJob, type MyMockStatus } from "@/lib/candidate";
import { mockPath } from "@/lib/mockKinds";

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

function CvCompletenessBanner({ items }: { items: CompletenessItem[] }) {
  const missing = items.filter((i) => CV_FIXABLE.has(i.key) && !i.done);
  if (missing.length === 0) return null;

  return (
    <div className="mt-6 rounded-[14px] border border-amber bg-amber/10 p-4">
      <p className="text-sm font-semibold text-ink">Your CV is missing {missing.map((i) => i.label.toLowerCase()).join(", ")}</p>
      <p className="mt-1 text-[13px] text-muted">
        This is what an employer opens the moment you apply. Import your existing CV or fill it in —
        takes a few minutes, and every job below still applies free either way.
      </p>
      <a href="/cv" className="mt-3 inline-block rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white">
        Complete my CV →
      </a>
    </div>
  );
}

/**
 * Two real steps, in order — take this role's AI interview, then Apply —
 * not one "View & apply" click that turns out to be blocked on the next
 * page. Score well on step 1 and the employer's own auto-shortlist rule
 * (set to a platform default the moment they published this JD) moves the
 * application straight to Shortlisted the instant it's graded — no
 * recruiter has to open it first.
 */
function stepCopy(job: InternalJob): { label: string; detail: string; cta: string } {
  if (job.has_applied) {
    return { label: "Applied", detail: "Your CV and interview score are with the employer.", cta: "" };
  }
  if (!job.mock_ready) {
    return {
      label: "Interview generating",
      detail: "This role's AI interview is still being generated — check back soon.",
      cta: "View role →",
    };
  }
  if (job.mock_status === "completed") {
    return {
      label: `Step 2 of 2 — score ${job.mock_score}`,
      detail: "Interview done. Score well enough and you're shortlisted automatically the moment you apply — no waiting on a recruiter.",
      cta: "Apply — free →",
    };
  }
  if (job.mock_status === "in_progress") {
    return {
      label: "Step 1 of 2 — in progress",
      detail: "Resume this role's AI interview to unlock Apply.",
      cta: "Resume interview →",
    };
  }
  return {
    label: "Step 1 of 2 — take the AI interview",
    detail: "A short AI interview for this exact role, first. Score well and you're shortlisted automatically — Apply unlocks once it's done.",
    cta: "Take AI interview →",
  };
}

function EmployerJobCard({ job }: { job: InternalJob }) {
  const step = stepCopy(job);

  return (
    <div className="rounded-[14px] border border-trust/30 bg-sky p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-semibold text-ink">
            {job.title}
            {job.company ? ` · ${job.company}` : ""}
          </p>
          <p className="mono text-[11px] uppercase tracking-widest text-muted">
            {[
              job.remote ? "Remote" : job.locations.join(", ") || null,
              job.experience_min_years !== null
                ? `${job.experience_min_years}–${job.experience_max_years ?? "+"} yrs`
                : null,
              job.openings ? `${job.openings} opening${job.openings === 1 ? "" : "s"}` : null,
            ]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
        <span className="mono shrink-0 rounded-full bg-trust px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-white">
          Direct
        </span>
      </div>

      {job.skills.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {job.skills.slice(0, 8).map((s) => (
            <span key={s} className="mono rounded-full bg-white px-2 py-0.5 text-[10px] text-ink">
              {s}
            </span>
          ))}
        </div>
      )}

      {!job.has_applied && (
        <p className="mono mt-3 text-[10px] font-semibold uppercase tracking-widest text-trust">{step.label}</p>
      )}
      <p className="mt-1 text-[12px] text-muted">{step.detail}</p>

      <div className="mt-4">
        {job.has_applied ? (
          <span className="mono inline-block rounded-full bg-verify-bg px-4 py-2 text-sm font-semibold text-verify">
            Applied
          </span>
        ) : (
          <Link
            href={`/jobs-for-you/${job.id}`}
            className="inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
          >
            {step.cta}
          </Link>
        )}
      </div>
    </div>
  );
}

function matchTone(pct: number): string {
  if (pct >= 70) return "bg-verify-bg text-verify";
  if (pct >= 40) return "bg-sky text-deep";
  return "bg-paper text-muted";
}

/**
 * General, CV-driven, not tied to any single job — separate from the
 * per-job interview above on purpose. Completing it is what makes a
 * student's profile visible to employers browsing BrowseJobs talent, even
 * for jobs they've never applied to.
 */
function CvReadinessCard({ status, busy, onStart }: { status: MyMockStatus | null; busy: boolean; onStart: () => void }) {
  const [recordingBusy, setRecordingBusy] = useState(false);
  const [recordingDeleted, setRecordingDeleted] = useState(false);
  const [recordingError, setRecordingError] = useState<string | null>(null);

  if (status === null) {
    return <div className="mt-6 shimmer h-32 rounded-[14px]" />;
  }

  const usedUp = status.attempts.used >= status.attempts.limit;
  const showRecordingActions = status.status === "completed" && status.has_recording && !recordingDeleted && status.mock_id !== null;

  async function viewRecording() {
    if (status === null || status.mock_id === null) return;
    setRecordingBusy(true);
    setRecordingError(null);
    try {
      const r = await mockRecordingApi.view(status.mock_id);
      window.open(r.data.url, "_blank", "noopener,noreferrer");
    } catch {
      setRecordingError("Couldn't open the recording — try again.");
    } finally {
      setRecordingBusy(false);
    }
  }

  async function deleteRecording() {
    if (status === null || status.mock_id === null) return;
    if (!window.confirm("Delete this recording? Your score stays the same — only the video goes.")) return;
    setRecordingBusy(true);
    setRecordingError(null);
    try {
      await mockRecordingApi.remove(status.mock_id);
      setRecordingDeleted(true);
    } catch {
      setRecordingError("Couldn't delete the recording — try again.");
    } finally {
      setRecordingBusy(false);
    }
  }

  return (
    <div className="mt-6 rounded-[14px] border border-trust/30 bg-white p-5">
      <p className="mono text-[11px] uppercase tracking-widest text-trust">AI Readiness Interview · not tied to any job</p>

      {status.status === "completed" ? (
        <>
          <p className="mt-2 text-sm font-semibold text-ink">Completed — score {status.score}</p>
          <p className="mt-1 text-sm text-muted">
            Your profile is now visible to employers browsing BrowseJobs talent — even for jobs you haven&apos;t
            applied to. {status.attempts.used}/{status.attempts.limit} attempts used.
          </p>
          {recordingError && <p className="mt-2 text-xs text-warn">{recordingError}</p>}
          <div className="mt-3 flex flex-wrap items-center gap-3">
            {!usedUp && (
              <button
                onClick={onStart}
                disabled={busy}
                className="rounded-full border border-trust px-5 py-2 text-sm font-semibold text-trust disabled:opacity-50"
              >
                {busy ? "Starting…" : "Retake — try for a higher score →"}
              </button>
            )}
            {showRecordingActions && (
              <>
                <button onClick={viewRecording} disabled={recordingBusy} className="text-sm font-semibold text-trust disabled:opacity-50">
                  ▶ View recording
                </button>
                <button onClick={deleteRecording} disabled={recordingBusy} className="text-sm text-muted hover:text-warn disabled:opacity-50">
                  Delete recording
                </button>
              </>
            )}
          </div>
        </>
      ) : (
        <>
          <p className="mt-2 text-sm font-semibold text-ink">
            {status.status === "in_progress" ? "In progress" : "15 questions, built from your own CV"}
          </p>
          <p className="mt-1 text-sm text-muted">
            {status.status === "in_progress"
              ? "Pick up where you left off."
              : "Basic to advanced, no specific job required. Complete it once and your profile becomes " +
                "visible to employers browsing BrowseJobs talent — even for jobs you never apply to."}
            {" "}{status.attempts.used}/{status.attempts.limit} attempts used.
          </p>
          {(status.status === "in_progress" || !usedUp) && (
            <button
              onClick={onStart}
              disabled={busy}
              className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {busy ? "Starting…" : status.status === "in_progress" ? "Resume interview →" : "Take AI interview →"}
            </button>
          )}
        </>
      )}
    </div>
  );
}

/** Wider Market results per page. */
const PAGE_SIZE = 10;

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
  // than merged into the external `jobs` list above, because the two apply
  // flows are genuinely different actions.
  const [employerJobs, setEmployerJobs] = useState<InternalJob[] | null>(null);
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

  async function startCvMock() {
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

  // A thin CV is not a bug in generation — it is an empty My CV profile.
  // Surfaced here (and again right before Apply) rather than blocked,
  // because applying free and improving the CV later both stay available.
  useEffect(() => {
    candidateApi
      .dashboard()
      .then((r) => setCompleteness(r.data.profile_completeness.items))
      .catch(() => {});
  }, []);

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

      {completeness && <CvCompletenessBanner items={completeness} />}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Get noticed, before you even apply</h2>
      <CvReadinessCard status={cvMock} busy={cvMockBusy} onStart={startCvMock} />

      {employerJobs === null ? (
        <div className="mt-6 shimmer h-40 rounded-[14px]" />
      ) : employerJobs.length > 0 ? (
        <div className="mt-6">
          <p className="mono text-[11px] uppercase tracking-widest text-trust">Hiring directly on BrowseJobs</p>
          <p className="mt-1 text-sm text-muted">
            Take each role&apos;s short AI interview first — it unlocks Apply. Score well against the role
            and your CV, and you&apos;re shortlisted with the employer automatically, no waiting on a recruiter
            to open your application.
          </p>
          <div className="mt-3 space-y-3">
            {employerJobs.map((job) => (
              <EmployerJobCard key={job.id} job={job} />
            ))}
          </div>
        </div>
      ) : null}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Wider market</h2>

      {hasCv === false ? (
        <div className="mt-8 rounded-[14px] border border-line bg-white p-8 text-center">
          <p className="text-sm font-semibold text-ink">Build your CV to see matched roles here</p>
          <p className="mt-1 text-sm text-muted">
            Every match here is &quot;how well does your CV fit this JD&quot; — there&apos;s nothing to match against yet.
          </p>
          <a href="/cv" className="mt-3 inline-block rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white">
            Build my CV →
          </a>
        </div>
      ) : jobs.length === 0 ? (
        <div className="mt-8 rounded-[14px] border border-line bg-white p-8 text-center">
          <p className="text-sm text-muted">No matched roles right now. Complete more topics and mocks — your feed sharpens as your profile grows.</p>
        </div>
      ) : (
        <div className="mt-6 space-y-3">
          {jobs.slice(page * PAGE_SIZE, page * PAGE_SIZE + PAGE_SIZE).map((job) => (
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

      {hasCv !== false && jobs.length > PAGE_SIZE && (
        <div className="mt-4 flex items-center justify-between gap-3">
          <span className="text-xs text-muted">
            Showing {page * PAGE_SIZE + 1}–{Math.min(jobs.length, page * PAGE_SIZE + PAGE_SIZE)} of {jobs.length}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(0, p - 1))}
              disabled={page === 0}
              className="rounded-full border border-line bg-white px-4 py-1.5 text-sm font-semibold text-ink hover:border-trust disabled:opacity-40"
            >
              ← Prev
            </button>
            <span className="mono text-xs text-muted">
              Page {page + 1} of {Math.ceil(jobs.length / PAGE_SIZE)}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(Math.ceil(jobs.length / PAGE_SIZE) - 1, p + 1))}
              disabled={page >= Math.ceil(jobs.length / PAGE_SIZE) - 1}
              className="rounded-full border border-line bg-white px-4 py-1.5 text-sm font-semibold text-ink hover:border-trust disabled:opacity-40"
            >
              Next →
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
