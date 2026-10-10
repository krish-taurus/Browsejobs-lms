"use client";

import { useEffect, useMemo, useState } from "react";
import "@/components/ap/pages/taurus-ui.css";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
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
    <div className={`tx-ap tx-ap--card ${taurusDisplayFont.variable}`}>
      <div className="tx-head">
        <div>
          <p className="tx-eyebrow">Powered by Taurus AI</p>
          <h1 className="tx-title">Hiring floor</h1>
          <p className="tx-sub">Every stage of your hiring, live. Each robot is the bot working that stage. Ask Taurus anything about it.</p>
        </div>
        <div className="tx-tools">
          <label className="tx-label" htmlFor="hiring-floor-job" style={{ margin: 0 }}>
            Role
          </label>
          <select id="hiring-floor-job" value={jobId ?? ""} onChange={(e) => setJobId(e.target.value ? Number(e.target.value) : null)} className="tx-select">
            <option value="">All roles</option>
            {jobs.map((j) => (
              <option key={j.id} value={j.id}>
                {j.title}
              </option>
            ))}
          </select>
        </div>
      </div>
      <div className="tx-floor tx-floor--admin">
        <TaurusConsole key={`${workspace.id}-${jobId ?? "all"}`} source={source} mode="recruitment" variant="full" title="HIRING FLOOR" askPlaceholder="Ask Taurus: where are we with this role?" subtitle={workspace.name} />
      </div>
      <p className="tx-fine">
        Built from your jobs and applications. Scores appear once grading finishes. Moving a candidate or releasing an offer stays a decision for your team.
      </p>
    </div>
  );
}
