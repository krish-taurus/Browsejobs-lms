"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { JobIcon, type JobIconName } from "@/components/jobs/JobIcons";
import { FOCUS_RING, StatusChip } from "@/components/jobs/ReadinessBanner";
import type { InternalJob } from "@/lib/candidate";

/**
 * Company mark: the employer's own logo when one is set in the CRM, else the
 * real BrowseJobs mark for BrowseJobs' own roles, else an initials tile.
 */
function CompanyTile({ company, logo }: { company: string | null; logo?: string | null }) {
  if (logo) {
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={logo} alt="" className="h-12 w-12 shrink-0 rounded-xl border border-line bg-white object-contain p-1" />;
  }
  if (company !== null && company.trim().toLowerCase() === "browsejobs") {
    // eslint-disable-next-line @next/next/no-img-element
    return <img src="/logo.svg" alt="" className="h-12 w-12 shrink-0 rounded-xl" />;
  }
  const initials = (company ?? "?").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase()).join("") || "?";
  return (
    <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-sky text-sm font-semibold text-trust" aria-hidden="true">
      {initials}
    </span>
  );
}

// Skills arrive lowercase from the JD ("sql", "numpy") — show them the way
// people write them.
const SKILL_NAMES: Record<string, string> = {
  numpy: "NumPy", pyspark: "PySpark", javascript: "JavaScript", typescript: "TypeScript",
  postgresql: "PostgreSQL", mysql: "MySQL", mongodb: "MongoDB", github: "GitHub",
  "github actions": "GitHub Actions", "a/b testing": "A/B testing", "ci/cd": "CI/CD",
  "scikit-learn": "Scikit-learn", powerbi: "Power BI", "power bi": "Power BI",
  pytorch: "PyTorch", tensorflow: "TensorFlow", fastapi: "FastAPI", nodejs: "Node.js", "node.js": "Node.js",
};
const ACRONYMS = new Set(["sql", "etl", "elt", "aws", "gcp", "api", "apis", "ml", "ai", "bi", "dbt", "nlp", "llm", "llms", "css", "html", "ui", "ux"]);

export function formatSkill(raw: string): string {
  const s = raw.trim();
  const key = s.toLowerCase();
  if (SKILL_NAMES[key]) return SKILL_NAMES[key];
  if (ACRONYMS.has(key)) return key.toUpperCase();
  return s.charAt(0).toUpperCase() + s.slice(1);
}

function Meta({ icon, children }: { icon: JobIconName; children: ReactNode }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <JobIcon name={icon} className="h-4 w-4 shrink-0" />
      {children}
    </span>
  );
}

/** Step 1 (this role's AI interview) → Step 2 (Apply), with Apply locked until step 1 is done. */
export function InterviewApplySteps({ interviewDone, applyOpen, applied = false }: { interviewDone: boolean; applyOpen: boolean; applied?: boolean }) {
  return (
    <ol className="flex items-center gap-3 text-sm font-semibold" aria-label="Application steps">
      <li className="flex items-center gap-2 text-trust">
        <span
          className={`grid h-6 w-6 place-items-center rounded-full text-xs ${interviewDone ? "bg-verify text-white" : "bg-trust text-white"}`}
          aria-hidden="true"
        >
          {interviewDone ? <JobIcon name="check" className="h-3.5 w-3.5" /> : "1"}
        </span>
        <span className={interviewDone ? "text-verify" : undefined}>AI interview</span>
        {interviewDone && <span className="sr-only">(done)</span>}
      </li>
      <li aria-hidden="true" className="h-px w-10 bg-line" />
      <li className={`flex items-center gap-2 ${applied ? "text-verify" : applyOpen ? "text-trust" : "text-muted"}`}>
        <span
          className={`grid h-6 w-6 place-items-center rounded-full text-xs ${
            applied ? "bg-verify text-white" : applyOpen ? "bg-trust text-white" : "bg-paper text-muted ring-1 ring-line"
          }`}
          aria-hidden="true"
        >
          {applied ? <JobIcon name="check" className="h-3.5 w-3.5" /> : "2"}
        </span>
        {applied ? "Applied" : "Apply"}
        {!applyOpen && !applied && (
          <>
            <JobIcon name="lock" className="h-4 w-4" />
            <span className="sr-only">(locked until the interview is done)</span>
          </>
        )}
      </li>
    </ol>
  );
}

/**
 * A role hiring directly on BrowseJobs. Two real steps in order — this role's
 * own AI interview, then Apply (ApplyToEmployerJob rejects an application
 * without a completed interview). The AI Readiness interview does not count.
 */
export function JobCard({
  job,
  busy,
  error,
  onStartInterview,
}: {
  job: InternalJob;
  busy: boolean;
  error: string | null;
  onStartInterview: (job: InternalJob) => void;
}) {
  const interviewDone = job.mock_status === "completed";
  const inProgress = job.mock_status === "in_progress";
  const location = job.remote ? "Remote" : job.locations.join(", ") || null;
  const experience = job.experience_min_years !== null
    ? job.experience_max_years !== null && job.experience_max_years !== job.experience_min_years
      ? `${job.experience_min_years}–${job.experience_max_years} years`
      : `${job.experience_min_years}+ years`
    : null;

  const helper = job.has_applied
    ? "Your CV and interview score are with the employer."
    : !job.mock_ready
      ? "This role's AI interview is still being generated — check back soon."
      : interviewDone
        ? "Interview done. Score well enough and you're shortlisted automatically the moment you apply."
        : inProgress
          ? "Resume your interview to unlock this application."
          : "Take a short interview tailored to this role.";

  const outline = `inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full border border-trust bg-white px-5 text-sm font-semibold text-trust transition-colors hover:bg-sky ${FOCUS_RING}`;
  const solid = `inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full bg-trust px-5 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:cursor-not-allowed disabled:opacity-60 ${FOCUS_RING}`;

  return (
    <article aria-labelledby={`job-${job.id}-title`} className="rounded-[18px] border border-line bg-white p-5 shadow-soft sm:p-6">
      <div className="flex items-start gap-4">
        <CompanyTile company={job.company} logo={job.company_logo} />
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <h3 id={`job-${job.id}-title`} className="text-lg font-semibold leading-tight text-ink">
                <Link href={`/jobs-for-you/${job.id}`} className={`-my-2.5 inline-block rounded py-2.5 hover:text-trust ${FOCUS_RING}`}>
                  {job.title}
                </Link>
              </h3>
              {job.company && <p className="mt-0.5 text-sm text-muted">{job.company}</p>}
            </div>
            <span className="shrink-0 rounded-full border border-trust px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-trust">
              Direct
            </span>
          </div>
          <p className="mt-2.5 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-muted">
            {location && <Meta icon="mapPin">{location}</Meta>}
            {experience && <Meta icon="briefcase">{experience}</Meta>}
            {job.openings ? <Meta icon="users">{job.openings} opening{job.openings === 1 ? "" : "s"}</Meta> : null}
          </p>
        </div>
      </div>

      {job.skills.length > 0 && (
        <ul className="mt-4 flex flex-wrap gap-2" aria-label="Skills">
          {job.skills.map((s) => (
            <li key={s} className="rounded-full border border-line bg-paper px-3 py-1 text-[13px] text-ink">
              {formatSkill(s)}
            </li>
          ))}
        </ul>
      )}

      <div className="mt-5 border-t border-line pt-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <InterviewApplySteps interviewDone={interviewDone || job.has_applied} applyOpen={interviewDone && !job.has_applied} applied={job.has_applied} />
          {job.has_applied ? (
            <StatusChip tone="done">Applied</StatusChip>
          ) : !job.mock_ready ? (
            <StatusChip tone="muted">Interview generating</StatusChip>
          ) : interviewDone ? (
            <StatusChip tone="done">Score <span className="mono">{job.mock_score ?? "—"}</span></StatusChip>
          ) : inProgress ? (
            <StatusChip tone="progress">In progress</StatusChip>
          ) : null}
        </div>
        <p className="mt-2 text-sm text-muted">{helper}</p>

        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
          {job.has_applied || !job.mock_ready ? (
            <Link href={`/jobs-for-you/${job.id}`} className={outline}>
              View role <JobIcon name="arrowRight" className="h-[18px] w-[18px]" />
            </Link>
          ) : interviewDone ? (
            // Apply runs on the role page, which checks the CV is on file first.
            <Link href={`/jobs-for-you/${job.id}`} className={solid}>
              Apply <JobIcon name="arrowRight" className="h-[18px] w-[18px]" />
            </Link>
          ) : (
            <button
              type="button"
              onClick={() => onStartInterview(job)}
              disabled={busy}
              aria-busy={busy}
              className={inProgress ? solid : `${outline} disabled:cursor-not-allowed disabled:opacity-60`}
            >
              {busy ? "Starting…" : inProgress ? "Resume interview" : "Start AI interview"}
              {!busy && <JobIcon name="arrowRight" className="h-[18px] w-[18px]" />}
            </button>
          )}

          {!job.has_applied && !interviewDone && (
            <button
              type="button"
              disabled
              className="inline-flex min-h-[44px] cursor-not-allowed items-center justify-center gap-2 rounded-full bg-paper px-6 text-sm font-semibold text-muted"
              title="Finish this role's AI interview to unlock Apply"
            >
              <JobIcon name="lock" className="h-4 w-4" />
              Apply
            </button>
          )}
        </div>
        {error && <p role="alert" className="mt-2 text-sm text-warn">{error}</p>}
      </div>
    </article>
  );
}
