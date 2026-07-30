"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { PageHead, Pill, PrimaryButton, Skeleton, Tile } from "@/components/employer/ui";
import { DEEP, SKY, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
import { employerApi, type EmployerJobRow, type JobStatus } from "@/lib/employer";

const STATUS_TONE: Record<JobStatus, "verify" | "neutral" | "trust" | "warn"> = {
  published: "verify",
  draft: "neutral",
  paused: "trust",
  closed: "neutral",
};

const ACCENTS = [TRUST, VIOLET, DEEP, VERIFY, SKY];

export default function EmployerJobsPage() {
  const { workspace } = useWorkspace();
  const [jobs, setJobs] = useState<EmployerJobRow[] | null>(null);

  useEffect(() => {
    setJobs(null);
    employerApi.jobs(workspace.id).then((res) => setJobs(res.data)).catch(() => setJobs([]));
  }, [workspace.id]);

  return (
    <div className="space-y-7 pb-10">
      <PageHead
        kicker="Jobs"
        title="Your job"
        highlight="descriptions"
        sub="Publishing a JD generates its own interview and grading rubric — applicants arrive already scored against it."
        action={<PrimaryButton href="/employer/jobs/new">Post a JD</PrimaryButton>}
      />

      {jobs === null ? (
        <div className="grid gap-4 md:grid-cols-2 md:gap-5">
          {[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-44" />)}
        </div>
      ) : jobs.length === 0 ? (
        <Tile accent={TRUST} className="py-14 text-center" hover={false}>
          <p className="font-display text-2xl font-bold tracking-tight">No JDs yet</p>
          <p className="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-black/50">
            Post your first job description. The moment you publish, BrowseJobs writes a job-specific
            interview and rubric for it, and every applicant is graded against that — not a CV keyword scan.
          </p>
          <div className="mt-6 flex justify-center">
            <PrimaryButton href="/employer/jobs/new">Post your first JD</PrimaryButton>
          </div>
        </Tile>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 md:gap-5">
          {jobs.map((job, i) => {
            const accent = ACCENTS[i % ACCENTS.length];
            return (
              <Link key={job.id} href={`/employer/jobs/${job.id}`} className="group block">
                <Tile accent={accent} index={i} ghost={String(i + 1).padStart(2, "0")} className="h-full">
                  <div className="flex items-start justify-between gap-3">
                    <p className="font-display text-xl font-bold leading-tight tracking-tight transition-colors group-hover:text-[#1b6df0]">
                      {job.title}
                    </p>
                    <Pill tone={STATUS_TONE[job.status]}>{job.status}</Pill>
                  </div>

                  <span
                    aria-hidden
                    className="mt-2.5 block h-1 w-0 rounded-full transition-all duration-500 group-hover:w-20"
                    style={{ background: accent }}
                  />

                  <div className="mt-4 flex flex-wrap gap-1.5">
                    {(job.skills ?? []).slice(0, 5).map((skill) => (
                      <span
                        key={skill}
                        className="rounded-full bg-black/[0.04] px-2.5 py-1 font-mono text-[10px] text-black/50"
                      >
                        {skill}
                      </span>
                    ))}
                  </div>

                  <div className="mt-5 flex items-center gap-4 text-[11px] text-black/45">
                    <span className="font-mono">
                      <span className="font-semibold text-[#0a1220]">{job.openings}</span> opening
                      {job.openings === 1 ? "" : "s"}
                    </span>
                    <span className="font-mono">
                      <span className="font-semibold text-[#0a1220]">
                        {job.experience_min_years}–{job.experience_max_years ?? "∞"}
                      </span>{" "}
                      yrs
                    </span>
                    {job.locations?.length ? <span>{job.locations.join(", ")}</span> : null}
                    {job.remote && <span className="text-[#0ba860]">Remote</span>}
                  </div>

                  {job.current_mock && (
                    <p className="mt-4 border-t border-black/[0.06] pt-3 font-mono text-[10px] uppercase tracking-[0.14em] text-black/35">
                      Mock v{job.current_mock.version} · {job.current_mock.status}
                    </p>
                  )}
                </Tile>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
