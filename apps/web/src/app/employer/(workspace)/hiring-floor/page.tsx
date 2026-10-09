"use client";

import { useEffect, useMemo, useState } from "react";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { TaurusConsole } from "@/components/taurus/TaurusConsole";
import { employerApi } from "@/lib/employer";
import { employerFloorSource } from "@/lib/taurus-floor/live";

type JobOption = { id: number; title: string };

/**
 * Hiring floor — the employer's real pipeline on the Taurus 3D floor:
 * one zone per hiring stage, one bot per stage, candidates travelling
 * between stages as they move. Built from this workspace's own jobs and
 * applications (GET /employer/workspaces/{id}/hiring-floor).
 */
export default function HiringFloorPage() {
  const { workspace } = useWorkspace();
  const [jobId, setJobId] = useState<number | null>(null);
  const [jobs, setJobs] = useState<JobOption[]>([]);

  useEffect(() => {
    let cancelled = false;
    employerApi
      .jobs(workspace.id)
      .then((res) => {
        if (!cancelled) setJobs(res.data.map((j) => ({ id: j.id, title: j.title })));
      })
      .catch(() => {
        if (!cancelled) setJobs([]);
      });
    return () => {
      cancelled = true;
    };
  }, [workspace.id]);

  const source = useMemo(() => employerFloorSource(workspace.id, jobId), [workspace.id, jobId]);

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="display text-2xl">Hiring floor</h1>
          <p className="mt-1 text-sm opacity-70">Every stage of your hiring, live. Each robot is the bot working that stage. Ask Taurus anything about it.</p>
        </div>
        <label className="flex items-center gap-2 text-sm">
          <span className="opacity-70">Role</span>
          <select
            id="hiring-floor-job"
            value={jobId ?? ""}
            onChange={(e) => setJobId(e.target.value ? Number(e.target.value) : null)}
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink"
          >
            <option value="">All roles</option>
            {jobs.map((j) => (
              <option key={j.id} value={j.id}>
                {j.title}
              </option>
            ))}
          </select>
        </label>
      </div>
      <div className="h-[calc(100vh-12rem)] min-h-[600px] overflow-hidden rounded-[22px] border border-line">
        <TaurusConsole key={`${workspace.id}-${jobId ?? "all"}`} source={source} mode="recruitment" variant="full" title="HIRING FLOOR" askPlaceholder="Ask Taurus: where are we with this role?" subtitle={workspace.name} />
      </div>
      <p className="mono mt-2 text-[11px] opacity-60">
        Built from your jobs and applications. Scores appear once grading finishes. Moving a candidate or releasing an offer stays a decision for your team.
      </p>
    </div>
  );
}
