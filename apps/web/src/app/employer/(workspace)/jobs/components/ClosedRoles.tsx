import Link from "next/link";
import type { EmployerJobRow } from "@/lib/employer";
import { ChevronRightIcon } from "@/components/employer/icons";
import { formatExperience } from "./format";

/** "Data Engineer" → "DE"; falls back to the first two letters if there's only one word. */
function initials(title: string): string {
  const words = title.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return "—";
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
  return (words[0][0] + words[1][0]).toUpperCase();
}

function InitialsTile({ title }: { title: string }) {
  return (
    <span
      aria-hidden
      className="grid size-9 shrink-0 place-items-center rounded-xl text-xs font-semibold"
      style={{ background: "#f2f4f0", color: "var(--bj-dash-muted)" }}
    >
      {initials(title)}
    </span>
  );
}

function DetailsLink({ job }: { job: EmployerJobRow }) {
  return (
    <Link
      href={`/employer/jobs/${job.id}`}
      aria-label={`View details for ${job.title}`}
      className="grid size-8 shrink-0 place-items-center rounded-full border transition-colors hover:border-[var(--bj-dash-primary)] hover:text-[var(--bj-dash-primary)]"
      style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}
    >
      <ChevronRightIcon className="size-4" />
    </Link>
  );
}

export function ClosedRoles({
  jobs,
  count,
  loading,
}: {
  jobs: EmployerJobRow[];
  count: number;
  loading: boolean;
}) {
  return (
    <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <div className="flex items-center gap-2.5">
        <h2 className="text-lg font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Closed roles</h2>
        <span className="rounded-full px-2.5 py-0.5 text-xs font-medium" style={{ background: "#f2f4f0", color: "var(--bj-dash-muted)" }}>
          {count}
        </span>
      </div>

      {loading ? (
        <div className="mt-4 space-y-2">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-14 animate-pulse rounded-xl" style={{ background: "var(--bj-dash-soft)" }} />
          ))}
        </div>
      ) : jobs.length === 0 ? (
        <p className="mt-4 text-sm" style={{ color: "var(--bj-dash-muted)" }}>No closed roles match this view.</p>
      ) : (
        <>
          {/* Desktop: a real table. */}
          <div className="mt-4 hidden overflow-x-auto md:block">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b text-xs uppercase tracking-wide" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}>
                  <th scope="col" className="pb-2.5 pr-4 font-medium">Job role</th>
                  <th scope="col" className="pb-2.5 pr-4 font-medium">Skills</th>
                  <th scope="col" className="pb-2.5 pr-4 font-medium">Openings</th>
                  <th scope="col" className="pb-2.5 pr-4 font-medium">Experience</th>
                  <th scope="col" className="pb-2.5 pr-4 font-medium">Location</th>
                  <th scope="col" className="pb-2.5 pr-4 font-medium">Status</th>
                  <th scope="col" className="pb-2.5 font-medium"><span className="sr-only">Details</span></th>
                </tr>
              </thead>
              <tbody>
                {jobs.map((job) => (
                  <tr key={job.id} className="border-b last:border-b-0" style={{ borderColor: "var(--bj-dash-border)" }}>
                    <td className="py-3 pr-4">
                      <span className="flex items-center gap-2.5">
                        <InitialsTile title={job.title} />
                        <span className="font-medium" style={{ color: "var(--bj-dash-ink)" }}>{job.title}</span>
                      </span>
                    </td>
                    <td className="max-w-[220px] py-3 pr-4" style={{ color: "var(--bj-dash-muted)" }}>
                      {(job.skills ?? []).join(", ") || "—"}
                    </td>
                    <td className="py-3 pr-4" style={{ color: "var(--bj-dash-ink)" }}>{job.openings}</td>
                    <td className="py-3 pr-4" style={{ color: "var(--bj-dash-ink)" }}>
                      {formatExperience(job.experience_min_years, job.experience_max_years)}
                    </td>
                    <td className="py-3 pr-4" style={{ color: "var(--bj-dash-ink)" }}>
                      {job.locations && job.locations.length > 0 ? job.locations.join(", ") : "—"}
                    </td>
                    <td className="py-3 pr-4">
                      <span className="rounded-full px-2.5 py-1 text-xs font-medium" style={{ background: "#f2f4f0", color: "var(--bj-dash-muted)" }}>
                        Closed
                      </span>
                    </td>
                    <td className="py-3"><DetailsLink job={job} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile: the same data, stacked — no horizontal page scroll. */}
          <ul className="mt-4 space-y-2.5 md:hidden">
            {jobs.map((job) => (
              <li key={job.id} className="rounded-2xl border p-3.5" style={{ borderColor: "var(--bj-dash-border)" }}>
                <div className="flex items-start justify-between gap-3">
                  <span className="flex items-center gap-2.5">
                    <InitialsTile title={job.title} />
                    <span className="font-medium" style={{ color: "var(--bj-dash-ink)" }}>{job.title}</span>
                  </span>
                  <DetailsLink job={job} />
                </div>
                <dl className="mt-3 grid grid-cols-2 gap-2 text-xs" style={{ color: "var(--bj-dash-muted)" }}>
                  <div><dt className="inline font-medium">Openings: </dt><dd className="inline">{job.openings}</dd></div>
                  <div><dt className="inline font-medium">Experience: </dt><dd className="inline">{formatExperience(job.experience_min_years, job.experience_max_years)}</dd></div>
                  <div className="col-span-2"><dt className="inline font-medium">Location: </dt><dd className="inline">{job.locations?.join(", ") || "—"}</dd></div>
                  <div className="col-span-2"><dt className="inline font-medium">Skills: </dt><dd className="inline">{(job.skills ?? []).join(", ") || "—"}</dd></div>
                </dl>
                <span className="mt-2 inline-block rounded-full px-2.5 py-1 text-xs font-medium" style={{ background: "#f2f4f0", color: "var(--bj-dash-muted)" }}>
                  Closed
                </span>
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  );
}
