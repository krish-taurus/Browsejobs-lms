"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useState, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";
import { candidateApi, jobApi, type MyMockStatus, type PublicJob } from "@/lib/candidate";
import { JobDescription } from "@/components/jobs/JobDescription";

/**
 * Apply to a BrowseJobs-direct role, in the LMS's own theme — not the
 * separate dark candidate shell. Both gates (a CV on file, a completed
 * interview for this exact JD) are enforced server-side by ApplyToEmployerJob
 * already; this page's job is just to make that visible before someone
 * tries and gets rejected.
 */
export default function ApplyToJobPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const jobId = Number(id);
  const router = useRouter();

  const [job, setJob] = useState<PublicJob | null>(null);
  const [hasCv, setHasCv] = useState<boolean | null>(null);
  const [mock, setMock] = useState<MyMockStatus | null>(null);
  const [applied, setApplied] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    jobApi.show(jobId).then((r) => setJob(r.data)).catch(() => {});
    apiJson<{ data: { latest: unknown } }>("/api/v1/me/cv")
      .then((r) => setHasCv(r.data.latest !== null))
      .catch(() => setHasCv(false));
    jobApi.myMock(jobId).then((r) => setMock(r.data)).catch(() => setMock({ status: "none", mock_id: null, score: null, attempts: { used: 0, limit: 3 } }));
    jobApi.myApplications()
      .then((r) => setApplied(r.data.some((a) => a.employer_job_id === jobId)))
      .catch(() => {});
  }, [jobId]);

  useEffect(load, [load]);

  async function startInterview() {
    setBusy(true);
    setError(null);
    try {
      const r = await candidateApi.startMock(jobId);
      router.push(`/student-ai-mock/${r.data.mock_id}/room`);
    } catch (err) {
      // Past the attempt cap this is a plain validation error (a hard stop,
      // not a purchase offer — StartEmployerJobMock never touches a wallet),
      // so the generic message already reads fine.
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the interview.");
      setBusy(false);
    }
  }

  async function apply() {
    setBusy(true);
    setError(null);
    try {
      await candidateApi.applyToJob(jobId);
      setApplied(true);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not apply.");
    } finally {
      setBusy(false);
    }
  }

  if (!job || hasCv === null || !mock) {
    return <div className="mx-auto max-w-2xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  }

  const mockDone = mock.status === "completed";
  const bothDone = hasCv && mockDone;

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/jobs-for-you" className="text-xs text-trust hover:underline">← Jobs for You</Link>

      <p className="kicker mt-3 text-trust">{job.company?.name ?? "Hiring directly on BrowseJobs"}</p>
      <h1 className="display mt-1.5 text-3xl text-ink">{job.title}</h1>
      <p className="mt-1 text-sm text-muted">
        {[
          job.remote ? "Remote" : (job.locations ?? []).join(", ") || null,
          job.experience_min_years !== null ? `${job.experience_min_years}–${job.experience_max_years ?? "+"} yrs` : null,
          job.openings ? `${job.openings} opening${job.openings === 1 ? "" : "s"}` : null,
        ].filter(Boolean).join(" · ")}
      </p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      {applied ? (
        <div className="mt-6 rounded-2xl border border-line bg-verify-bg p-6 text-center">
          <p className="display text-lg text-verify">Applied</p>
          <p className="mt-1 text-sm text-ink">Your CV and interview score are with the employer.</p>
        </div>
      ) : (
        <div className="mt-6 space-y-3">
          <ChecklistItem
            done={hasCv}
            label="Build your CV"
            detail={hasCv ? "On file — ready to send." : "Required before you can apply."}
            action={!hasCv && (
              <Link href="/cv" className="rounded-full bg-trust px-4 py-2 text-xs font-semibold text-white">
                Go to My CV
              </Link>
            )}
          />
          <ChecklistItem
            done={mockDone}
            label="Take the AI interview"
            detail={
              mockDone
                ? `Completed — score ${mock.score}. ${mock.attempts.used}/${mock.attempts.limit} attempts used.`
                : mock.status === "in_progress"
                  ? "In progress — resume it."
                  : mock.attempts.used >= mock.attempts.limit
                    ? `You've used all ${mock.attempts.limit} attempts for this role.`
                    : `15 minutes, spoken. Tests communication and this role's skills. ${mock.attempts.used}/${mock.attempts.limit} attempts used.`
            }
            action={!mockDone && mock.attempts.used < mock.attempts.limit && (
              <button
                onClick={startInterview}
                disabled={busy}
                className="rounded-full bg-trust px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
              >
                {busy ? "Starting…" : mock.status === "in_progress" ? "Resume interview" : "Take AI interview"}
              </button>
            )}
          />

          {bothDone && (
            <button
              onClick={apply}
              disabled={busy}
              className="mt-2 w-full rounded-full bg-trust px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
            >
              {busy ? "Applying…" : "Apply — free"}
            </button>
          )}
        </div>
      )}

      {job.skills && job.skills.length > 0 && (
        <div className="mt-6">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">What the interview will test</p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {job.skills.map((s) => (
              <span key={s} className="mono rounded-full bg-paper px-2.5 py-1 text-[11px] text-ink">{s}</span>
            ))}
          </div>
        </div>
      )}

      <div className="mt-6 rounded-2xl border border-line bg-white p-6">
        <p className="text-xs font-semibold uppercase tracking-widest text-muted">The role</p>
        <JobDescription description={job.description} className="mt-3 text-sm leading-relaxed text-ink" />
      </div>
    </div>
  );
}

function ChecklistItem({
  done, label, detail, action,
}: {
  done: boolean;
  label: string;
  detail: string;
  action: ReactNode;
}) {
  return (
    <div className={`flex items-center justify-between gap-3 rounded-2xl border p-4 ${done ? "border-verify/30 bg-verify-bg" : "border-line bg-white"}`}>
      <div className="flex items-center gap-3">
        <span className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold ${done ? "bg-verify text-white" : "border border-line text-muted"}`}>
          {done ? "✓" : ""}
        </span>
        <div>
          <p className="text-sm font-semibold text-ink">{label}</p>
          <p className="text-xs text-muted">{detail}</p>
        </div>
      </div>
      {action}
    </div>
  );
}
