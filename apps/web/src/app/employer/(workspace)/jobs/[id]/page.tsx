"use client";

import { use, useCallback, useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import {
  employerApi,
  nextStage,
  STAGE_LABELS,
  type ApplicationRow,
  type AutomationRule,
  type EmployerJobRow,
  type InterviewRow,
  type JdMockData,
} from "@/lib/employer";

type Tab = "applications" | "mock" | "automation";

export default function JobDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const jobId = Number(id);
  const { workspace } = useWorkspace();
  const [job, setJob] = useState<EmployerJobRow | null>(null);
  const [tab, setTab] = useState<Tab>("applications");
  const [notFound, setNotFound] = useState(false);

  const loadJob = useCallback(() => {
    employerApi.job(workspace.id, jobId)
      .then((res) => setJob(res.data))
      .catch(() => setNotFound(true));
  }, [workspace.id, jobId]);

  useEffect(loadJob, [loadJob]);

  if (notFound) {
    return <p className="rounded-card border border-line bg-white p-6 text-sm text-muted shadow-soft">This JD does not exist in this workspace.</p>;
  }
  if (!job) {
    return <div className="space-y-4"><div className="shimmer h-20 rounded-card" /><div className="shimmer h-64 rounded-card" /></div>;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
          <p className="mono text-[11px] uppercase tracking-widest text-trust">JD · {job.status}</p>
          <h1 className="display mt-1 text-2xl text-ink">{job.title}</h1>
          <p className="mt-1 text-xs text-muted">
            {(job.skills ?? []).join(" · ")}
            {job.locations?.length ? ` · ${job.locations.join(", ")}` : ""} ·{" "}
            <span className="mono">{job.experience_min_years}–{job.experience_max_years ?? "∞"}y</span>
          </p>
        </div>
        <JobLifecycleActions job={job} onChanged={loadJob} />
      </div>

      <div className="flex gap-1 border-b border-line" role="tablist">
        {(["applications", "mock", "automation"] as Tab[]).map((t) => (
          <button
            key={t}
            role="tab"
            aria-selected={tab === t}
            onClick={() => setTab(t)}
            className={`rounded-t-lg px-4 py-2 text-sm font-medium ${
              tab === t ? "border border-b-0 border-line bg-white text-trust" : "text-muted hover:text-ink"
            }`}
          >
            {t === "applications" ? "Applications" : t === "mock" ? "JD mock" : "Automation"}
          </button>
        ))}
      </div>

      {tab === "applications" && <ApplicationsTab jobId={jobId} />}
      {tab === "mock" && <MockTab jobId={jobId} />}
      {tab === "automation" && <AutomationTab jobId={jobId} />}
    </div>
  );
}

function JobLifecycleActions({ job, onChanged }: { job: EmployerJobRow; onChanged: () => void }) {
  const { workspace } = useWorkspace();
  const [busy, setBusy] = useState(false);

  async function run(action: () => Promise<unknown>) {
    setBusy(true);
    try { await action(); onChanged(); } finally { setBusy(false); }
  }

  return (
    <div className="flex gap-2">
      {(job.status === "draft" || job.status === "paused") && (
        <button disabled={busy} onClick={() => run(() => employerApi.publishJob(workspace.id, job.id))}
          className="rounded-input bg-trust px-4 py-2 text-sm font-semibold text-white shadow-soft disabled:opacity-50">
          Publish
        </button>
      )}
      {job.status === "published" && (
        <button disabled={busy} onClick={() => run(() => employerApi.changeJobStatus(workspace.id, job.id, "paused"))}
          className="rounded-input border border-line px-4 py-2 text-sm font-medium text-ink disabled:opacity-50">
          Pause
        </button>
      )}
      {(job.status === "published" || job.status === "paused") && (
        <button disabled={busy}
          onClick={() => { if (confirm("Close this JD? This cannot be undone.")) void run(() => employerApi.changeJobStatus(workspace.id, job.id, "closed")); }}
          className="rounded-input border border-line px-4 py-2 text-sm font-medium text-muted disabled:opacity-50">
          Close
        </button>
      )}
    </div>
  );
}

/* ---------------------------------------------------------------- */

function ApplicationsTab({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [rows, setRows] = useState<ApplicationRow[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    employerApi.applications(workspace.id, jobId)
      .then((res) => setRows(res.data))
      .catch(() => setRows([]));
  }, [workspace.id, jobId]);

  useEffect(load, [load]);

  if (rows === null) {
    return <div className="space-y-3">{[0, 1, 2].map((i) => <div key={i} className="shimmer h-20 rounded-card" />)}</div>;
  }

  if (rows.length === 0) {
    return (
      <div className="rounded-panel border border-line bg-white p-8 text-center shadow-soft">
        <p className="display text-lg text-ink">No applications yet</p>
        <p className="mx-auto mt-2 max-w-md text-sm text-muted">
          Candidates on BrowseJobs can see this JD and apply free. Applicants who complete the
          JD-specific mock arrive graded and ranked at the top of this list.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {error && <p className="text-sm text-warn">{error}</p>}
      {rows.map((row) => (
        <ApplicationCard key={row.id} row={row} jobId={jobId} onChanged={load} onError={setError} />
      ))}
      <p className="mono text-[10px] text-muted">
        Graded applicants rank first by mock score. Contact details unlock at Shortlisted.
      </p>
    </div>
  );
}

function ApplicationCard({
  row, jobId, onChanged, onError,
}: {
  row: ApplicationRow; jobId: number; onChanged: () => void; onError: (message: string | null) => void;
}) {
  const { workspace } = useWorkspace();
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [interviews, setInterviews] = useState<InterviewRow[] | null>(null);
  const [busy, setBusy] = useState(false);

  const forward = nextStage(row.stage);
  const terminal = ["hired", "rejected", "withdrawn"].includes(row.stage);

  useEffect(() => {
    if (open && interviews === null) {
      employerApi.interviews(workspace.id, jobId, row.id)
        .then((res) => setInterviews(res.data))
        .catch(() => setInterviews([]));
    }
  }, [open, interviews, workspace.id, jobId, row.id]);

  async function move(stage: ApplicationRow["stage"], note?: string) {
    setBusy(true);
    onError(null);
    try {
      await employerApi.moveStage(workspace.id, jobId, row.id, stage, note);
      onChanged();
    } catch (err) {
      onError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not update the stage.");
    } finally {
      setBusy(false);
    }
  }

  function reject() {
    const note = prompt("Rejection reason (the candidate sees this — keep it respectful):");
    if (note && note.trim() !== "") void move("rejected", note.trim());
  }

  return (
    <div className="rounded-card border border-line bg-white shadow-soft">
      <div className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
        <button onClick={() => setOpen((o) => !o)} className="flex items-center gap-4 text-left" aria-expanded={open}>
          <ScoreBadge score={row.mock_score} graded={row.is_graded} />
          <div>
            <p className="font-semibold text-ink">{row.candidate?.name ?? "Candidate"}</p>
            <p className="text-xs text-muted">
              {row.candidate?.email ?? "Contact unlocks at Shortlisted"} ·{" "}
              applied {new Date(row.applied_at).toLocaleDateString("en-IN", { day: "numeric", month: "short" })}
            </p>
          </div>
        </button>
        <div className="flex items-center gap-2">
          <span className="mono rounded-full bg-sky px-3 py-1 text-[10px] uppercase tracking-widest text-deep">
            {STAGE_LABELS[row.stage]}
          </span>
          {!terminal && forward && (
            <button disabled={busy} onClick={() => void move(forward)}
              className="rounded-input bg-trust px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">
              Advance → {STAGE_LABELS[forward]}
            </button>
          )}
          {!terminal && (
            <button disabled={busy} onClick={reject}
              className="rounded-input border border-line px-3 py-1.5 text-xs font-medium text-muted disabled:opacity-50">
              Reject
            </button>
          )}
        </div>
      </div>

      {open && (
        <motion.div
          initial={reduce ? false : { opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: durations.base, ease }}
          className="border-t border-line px-5 py-4"
        >
          {row.rejection_reason && (
            <p className="mb-3 text-sm text-warn">Rejected: {row.rejection_reason}</p>
          )}
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Interview rounds</p>
          {interviews === null ? (
            <div className="shimmer mt-2 h-16 rounded-lg" />
          ) : interviews.length === 0 ? (
            <p className="mt-2 text-sm text-muted">No L1/L2 rounds yet — advance the candidate to L1 to invite them.</p>
          ) : (
            <ul className="mt-2 space-y-3">
              {interviews.map((iv) => (
                <li key={iv.id} className="rounded-lg border border-line p-4">
                  <div className="flex items-center justify-between">
                    <p className="mono text-xs uppercase tracking-widest text-deep">{iv.round} · {iv.status}</p>
                    {iv.overall_score !== null && <p className="mono text-lg font-semibold text-ink">{iv.overall_score}</p>}
                  </div>
                  {iv.grading_delayed && (
                    <p className="mt-1 text-xs text-muted">Grading is taking longer than usual — scores appear as soon as it completes. We never fabricate them.</p>
                  )}
                  {iv.dimension_scores && (
                    <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                      {Object.entries(iv.dimension_scores).map(([key, value]) => (
                        <div key={key} className="rounded-lg bg-paper px-3 py-2">
                          <p className="mono text-sm font-semibold text-ink">{value}</p>
                          <p className="text-[10px] text-muted">{key.replaceAll("_", " ")}</p>
                        </div>
                      ))}
                    </div>
                  )}
                  {iv.grading_summary && <p className="mt-2 text-sm text-ink2">{iv.grading_summary}</p>}
                </li>
              ))}
            </ul>
          )}
        </motion.div>
      )}
    </div>
  );
}

function ScoreBadge({ score, graded }: { score: number | null; graded: boolean }) {
  if (!graded || score === null) {
    return (
      <div className="grid h-12 w-12 shrink-0 place-items-center rounded-full border border-dashed border-line">
        <span className="mono text-[9px] uppercase text-muted">ungraded</span>
      </div>
    );
  }
  return (
    <div className={`grid h-12 w-12 shrink-0 place-items-center rounded-full border-2 ${score >= 70 ? "border-trust" : "border-line"}`}>
      <span className="mono text-base font-semibold text-ink">{score}</span>
    </div>
  );
}

/* ---------------------------------------------------------------- */

function MockTab({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [mock, setMock] = useState<JdMockData | null | "none">(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    employerApi.mock(workspace.id, jobId)
      .then((res) => setMock(res.data))
      .catch(() => setMock("none"));
  }, [workspace.id, jobId]);

  useEffect(load, [load]);

  async function regenerate() {
    setBusy(true);
    setError(null);
    try {
      await employerApi.regenerateMock(workspace.id, jobId);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not regenerate.");
    } finally {
      setBusy(false);
    }
  }

  if (mock === null) return <div className="shimmer h-64 rounded-card" />;

  if (mock === "none") {
    return (
      <p className="rounded-card border border-line bg-white p-6 text-sm text-muted shadow-soft">
        No mock yet — it is generated automatically when the JD is published.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between rounded-card border border-line bg-white p-5 shadow-soft">
        <p className="text-sm text-ink">
          <span className="mono">v{mock.version}</span> · {mock.status}
          {mock.source === "fallback" && <span className="ml-2 text-muted">(template-based — regenerate for AI-tailored questions)</span>}
        </p>
        <button disabled={busy || mock.status === "pending"} onClick={() => void regenerate()}
          className="rounded-input border border-line px-4 py-2 text-sm font-medium text-ink disabled:opacity-50">
          {busy ? "Queuing…" : "Regenerate (new version)"}
        </button>
      </div>
      {error && <p className="text-sm text-warn">{error}</p>}

      {mock.rubric && (
        <div className="rounded-card border border-line bg-white p-5 shadow-soft">
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Grading rubric</p>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            {mock.rubric.dimensions.map((d) => (
              <div key={d.key} className="rounded-lg bg-paper p-4">
                <div className="flex items-baseline justify-between">
                  <p className="text-sm font-semibold text-ink">{d.label}</p>
                  <p className="mono text-sm text-deep">{d.weight}%</p>
                </div>
                <p className="mt-1 text-xs text-muted">{d.criteria}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      {mock.questions && (
        <div className="rounded-card border border-line bg-white p-5 shadow-soft">
          <p className="mono text-[10px] uppercase tracking-widest text-muted">
            Questions every applicant is asked ({mock.questions.length})
          </p>
          <ol className="mt-3 space-y-3">
            {mock.questions.map((q) => (
              <li key={q.id} className="flex gap-3 text-sm">
                <span className="mono shrink-0 text-muted">{String(q.id).padStart(2, "0")}</span>
                <div>
                  <p className="text-ink">{q.text}</p>
                  <p className="mono mt-0.5 text-[10px] uppercase tracking-widest text-muted">
                    {q.skill} · {q.type}{q.weight > 1 ? " · critical" : ""}
                  </p>
                </div>
              </li>
            ))}
          </ol>
        </div>
      )}
    </div>
  );
}

/* ---------------------------------------------------------------- */

function AutomationTab({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [rules, setRules] = useState<AutomationRule[] | null>(null);
  const [minScore, setMinScore] = useState(70);
  const [trigger, setTrigger] = useState<"application_graded" | "interview_graded">("application_graded");
  const [round, setRound] = useState<"l1" | "l2">("l1");
  const [targetStage, setTargetStage] = useState("shortlisted");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    employerApi.rules(workspace.id, jobId).then((res) => setRules(res.data)).catch(() => setRules([]));
  }, [workspace.id, jobId]);

  useEffect(load, [load]);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await employerApi.createRule(workspace.id, jobId, {
        trigger,
        round: trigger === "interview_graded" ? round : null,
        min_score: minScore,
        action: "advance",
        target_stage: targetStage,
      });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not create the rule.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4">
      <form onSubmit={create} className="rounded-card border border-line bg-white p-5 shadow-soft">
        <p className="mono text-[10px] uppercase tracking-widest text-muted">New rule</p>
        <div className="mt-3 flex flex-wrap items-center gap-2 text-sm text-ink">
          <span>When</span>
          <select value={trigger} onChange={(e) => setTrigger(e.target.value as typeof trigger)}
            className="rounded-input border border-line px-2 py-1.5">
            <option value="application_graded">the JD mock is graded</option>
            <option value="interview_graded">an interview round is graded</option>
          </select>
          {trigger === "interview_graded" && (
            <select value={round} onChange={(e) => setRound(e.target.value as typeof round)}
              className="rounded-input border border-line px-2 py-1.5">
              <option value="l1">L1</option>
              <option value="l2">L2</option>
            </select>
          )}
          <span>at</span>
          <input type="number" min={0} max={100} value={minScore} onChange={(e) => setMinScore(Number(e.target.value))}
            className="mono w-20 rounded-input border border-line px-2 py-1.5" />
          <span>% or above, advance to</span>
          <select value={targetStage} onChange={(e) => setTargetStage(e.target.value)}
            className="rounded-input border border-line px-2 py-1.5">
            <option value="shortlisted">Shortlisted</option>
            <option value="l1">L1</option>
            <option value="l2">L2</option>
          </select>
          <button disabled={busy} className="rounded-input bg-trust px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-50">
            Add rule
          </button>
        </div>
        {error && <p className="mt-2 text-sm text-warn">{error}</p>}
        <p className="mt-3 text-xs text-muted">
          Automation can advance or park candidates — it can never reject anyone and never releases offers.
          Every automated move is labelled in the candidate&apos;s timeline.
        </p>
      </form>

      {rules === null ? (
        <div className="shimmer h-24 rounded-card" />
      ) : rules.length === 0 ? (
        <p className="rounded-card border border-line bg-white p-6 text-sm text-muted shadow-soft">
          No rules yet. Add one above — e.g. “mock ≥ 70% → auto-shortlist” — and this JD runs itself.
        </p>
      ) : (
        <ul className="space-y-2">
          {rules.map((rule) => (
            <li key={rule.id} className="flex items-center justify-between rounded-card border border-line bg-white px-5 py-3 shadow-soft">
              <p className="text-sm text-ink">
                {rule.trigger === "application_graded" ? "JD mock" : `Interview ${rule.round?.toUpperCase()}`}{" "}
                ≥ <span className="mono">{rule.min_score}%</span> → {rule.action === "advance" ? `advance to ${rule.target_stage}` : "park for review"}
                {typeof rule.runs_count === "number" && (
                  <span className="mono ml-2 text-xs text-muted">{rule.runs_count} runs</span>
                )}
              </p>
              <button
                onClick={() => employerApi.toggleRule(workspace.id, jobId, rule.id).then(load)}
                className={`mono rounded-full px-3 py-1 text-[10px] uppercase tracking-widest ${
                  rule.enabled ? "bg-verify-bg text-verify" : "bg-paper text-muted"
                }`}
              >
                {rule.enabled ? "On" : "Off"}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
