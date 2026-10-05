import Link from "next/link";
import type { EmployerJobRow } from "@/lib/employer";
import { BriefcaseIcon, ClockIcon, LocationPinIcon, UsersIcon } from "@/components/employer/icons";
import { formatExperience } from "./format";

/** Template readiness is its own field, separate from publish status and candidate results — never inferred as "ready" by default. */
function readinessLabel(mock: EmployerJobRow["current_mock"]): { text: string; tone: "ready" | "pending" | "warn" | "muted" } {
  if (mock === null) return { text: "No interview template yet", tone: "muted" };
  switch (mock.status) {
    case "ready": return { text: "Interview ready", tone: "ready" };
    case "generating": return { text: "Generating interview…", tone: "pending" };
    case "pending": return { text: "Interview template pending", tone: "pending" };
    case "failed": return { text: "Interview template failed", tone: "warn" };
    default: return { text: "No interview template yet", tone: "muted" };
  }
}

const DOT_COLOR: Record<string, string> = {
  ready: "var(--bj-dash-primary)",
  pending: "#c9971f",
  warn: "#c0392b",
  muted: "var(--bj-dash-border)",
};

function JobCard({ job }: { job: EmployerJobRow }) {
  const readiness = readinessLabel(job.current_mock);
  const skills = job.skills ?? [];

  return (
    <div
      className="flex h-full flex-col rounded-[var(--bj-dash-radius)] border bg-white p-5 transition-colors hover:border-[var(--bj-dash-primary)]"
      style={{ borderColor: "var(--bj-dash-border)" }}
    >
      <div className="flex items-start justify-between gap-3">
        <span className="grid size-9 shrink-0 place-items-center rounded-xl" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
          <BriefcaseIcon className="size-4" />
        </span>
        <span className="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
          Published
        </span>
      </div>

      <h3 className="mt-3 text-lg font-semibold leading-snug" style={{ color: "var(--bj-dash-ink)" }}>{job.title}</h3>

      {skills.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {skills.map((skill) => (
            <span key={skill} className="rounded-full px-2.5 py-1 text-xs" style={{ background: "var(--bj-dash-canvas)", color: "var(--bj-dash-muted)" }}>
              {skill}
            </span>
          ))}
        </div>
      )}

      <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs" style={{ color: "var(--bj-dash-muted)" }}>
        <span className="flex items-center gap-1.5">
          <UsersIcon className="size-3.5" />
          {job.openings} opening{job.openings === 1 ? "" : "s"}
        </span>
        <span className="flex items-center gap-1.5">
          <ClockIcon className="size-3.5" />
          {formatExperience(job.experience_min_years, job.experience_max_years)}
        </span>
        {job.locations && job.locations.length > 0 && (
          <span className="flex items-center gap-1.5">
            <LocationPinIcon className="size-3.5" />
            {job.locations.join(", ")}
          </span>
        )}
        {job.remote && <span style={{ color: "var(--bj-dash-primary)" }}>Remote</span>}
      </div>

      <div className="mt-auto flex items-center justify-between border-t pt-3.5" style={{ borderColor: "var(--bj-dash-border)" }}>
        <span className="flex items-center gap-1.5 text-xs" style={{ color: readiness.tone === "warn" ? "#c0392b" : "var(--bj-dash-muted)" }}>
          <span className="size-1.5 rounded-full" style={{ background: DOT_COLOR[readiness.tone] }} aria-hidden />
          {readiness.text}
        </span>
        <Link
          href={`/employer/jobs/${job.id}`}
          className="flex items-center gap-1 text-sm font-semibold"
          style={{ color: "var(--bj-dash-primary)" }}
        >
          View details <span aria-hidden>↗</span>
        </Link>
      </div>
    </div>
  );
}

export function PublishedRoles({
  jobs,
  count,
  loading,
}: {
  jobs: EmployerJobRow[];
  /** Matching total for this status+search, not just jobs.length (may be one page of more). */
  count: number;
  loading: boolean;
}) {
  return (
    <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <div className="flex flex-wrap items-center gap-2.5">
        <h2 className="text-lg font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Published roles</h2>
        <span className="rounded-full px-2.5 py-0.5 text-xs font-medium" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
          {count}
        </span>
        <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Visible to applicants</span>
      </div>

      {loading ? (
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-52 animate-pulse rounded-[var(--bj-dash-radius)]" style={{ background: "var(--bj-dash-soft)" }} />
          ))}
        </div>
      ) : jobs.length === 0 ? (
        <p className="mt-4 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
          No published roles match this view.
        </p>
      ) : (
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {jobs.map((job) => <JobCard key={job.id} job={job} />)}
        </div>
      )}
    </div>
  );
}
