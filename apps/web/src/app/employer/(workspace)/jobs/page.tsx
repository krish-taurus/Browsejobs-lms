"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { employerApi, type EmployerJobRow } from "@/lib/employer";

const STATUS_STYLES: Record<string, string> = {
  published: "bg-verify-bg text-verify",
  draft: "bg-paper text-muted",
  paused: "bg-sky text-deep",
  closed: "bg-paper text-muted line-through",
};

export default function EmployerJobsPage() {
  const { workspace } = useWorkspace();
  const [jobs, setJobs] = useState<EmployerJobRow[] | null>(null);

  useEffect(() => {
    setJobs(null);
    employerApi.jobs(workspace.id).then((res) => setJobs(res.data)).catch(() => setJobs([]));
  }, [workspace.id]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="mono text-[11px] uppercase tracking-widest text-trust">Jobs</p>
          <h1 className="display mt-1 text-2xl text-ink">Your JDs</h1>
        </div>
        <Link href="/employer/jobs/new" className="rounded-input bg-trust px-4 py-2 text-sm font-semibold text-white shadow-soft">
          Post a JD
        </Link>
      </div>

      {jobs === null ? (
        <div className="space-y-3">{[0, 1, 2].map((i) => <div key={i} className="shimmer h-20 rounded-card" />)}</div>
      ) : jobs.length === 0 ? (
        <div className="rounded-panel border border-line bg-white p-8 text-center shadow-soft">
          <p className="display text-lg text-ink">No JDs yet</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-muted">
            Post your first JD. The moment you publish it, a JD-specific mock interview is generated
            and every applicant arrives pre-interviewed and graded against it.
          </p>
          <Link href="/employer/jobs/new" className="mt-4 inline-block rounded-input bg-trust px-5 py-2.5 text-sm font-semibold text-white">
            Post your first JD
          </Link>
        </div>
      ) : (
        <ul className="space-y-3">
          {jobs.map((job) => (
            <li key={job.id}>
              <Link
                href={`/employer/jobs/${job.id}`}
                className="flex flex-col gap-2 rounded-card border border-line bg-white p-5 shadow-soft transition-transform hover:-translate-y-0.5 sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p className="font-semibold text-ink">{job.title}</p>
                  <p className="mt-0.5 text-xs text-muted">
                    {(job.skills ?? []).slice(0, 5).join(" · ") || job.role_family || "—"}
                    {job.locations?.length ? ` · ${job.locations.join(", ")}` : ""}
                    {job.remote ? " · Remote" : ""}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="mono text-xs text-muted">{job.openings} opening{job.openings === 1 ? "" : "s"}</span>
                  <span className={`mono rounded-full px-3 py-1 text-[10px] uppercase tracking-widest ${STATUS_STYLES[job.status] ?? ""}`}>
                    {job.status}
                  </span>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
