"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { MockDesigner } from "@/components/employer/MockDesigner";
import { ProcessDesigner } from "@/components/employer/ProcessDesigner";
import {
  EASE,
  GhostButton,
  InkPanel,
  Label,
  PageHead,
  Pill,
  PrimaryButton,
  Skeleton,
  Tile,
} from "@/components/employer/ui";
import { DEEP, Ring, SkillMeter, TRUST, VIOLET } from "@/components/employer/charts";
import {
  employerApi,
  nextStage,
  STAGE_LABELS,
  type ApplicationRow,
  type ApplicationStage,
  type AutomationRule,
  type EmployerJobRow,
  type InterviewRow,
  type JdMockData,
  type TalentPoolCandidate,
} from "@/lib/employer";

type Tab = "applications" | "talent" | "process" | "design" | "mock" | "automation";

const TABS: { id: Tab; label: string }[] = [
  { id: "applications", label: "Applications" },
  { id: "talent", label: "Talent pool" },
  { id: "process", label: "Process" },
  { id: "design", label: "Design" },
  { id: "mock", label: "JD mock" },
  { id: "automation", label: "Automation" },
];

export default function JobDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const jobId = Number(id);
  const { workspace } = useWorkspace();
  const [job, setJob] = useState<EmployerJobRow | null>(null);
  const [tab, setTab] = useState<Tab>("applications");
  const [missing, setMissing] = useState(false);

  const loadJob = useCallback(() => {
    employerApi.job(workspace.id, jobId).then((res) => setJob(res.data)).catch(() => setMissing(true));
  }, [workspace.id, jobId]);

  useEffect(loadJob, [loadJob]);

  if (missing) {
    return (
      <p className="rounded-3xl border border-white/[0.08] bg-[#0a0f1c] p-8 text-sm text-white/60">
        This JD does not exist in this workspace.
      </p>
    );
  }
  if (!job) {
    return <div className="space-y-5"><Skeleton className="h-24" /><Skeleton className="h-72" /></div>;
  }

  return (
    <div className="space-y-7 pb-10">
      <PageHead
        kicker={`JD · ${job.status}`}
        title={job.title}
        sub={`${(job.skills ?? []).join(" · ")}${job.locations?.length ? ` — ${job.locations.join(", ")}` : ""} · ${job.experience_min_years}–${job.experience_max_years ?? "∞"} yrs`}
        action={<JobLifecycleActions job={job} onChanged={loadJob} />}
      />

      <div className="flex flex-wrap gap-1.5" role="tablist">
        {TABS.map((t) => (
          <button
            key={t.id}
            role="tab"
            aria-selected={tab === t.id}
            onClick={() => setTab(t.id)}
            className={`rounded-full px-4 py-2 text-sm font-medium transition-all ${
              tab === t.id
                ? "bg-[#0a0f1c] text-white shadow-[0_8px_24px_rgba(10,18,32,0.2)]"
                : "border border-black/[0.08] bg-[#0a0f1c] text-white/65 hover:border-white/25 hover:text-white"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === "applications" && <ApplicationsTab jobId={jobId} />}
      {tab === "talent" && <TalentPoolTab jobId={jobId} />}
      {tab === "process" && <ProcessDesigner jobId={jobId} />}
      {tab === "design" && <MockDesigner jobId={jobId} />}
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
        <PrimaryButton disabled={busy} onClick={() => void run(() => employerApi.publishJob(workspace.id, job.id))}>
          Publish
        </PrimaryButton>
      )}
      {job.status === "published" && (
        <GhostButton disabled={busy} onClick={() => void run(() => employerApi.changeJobStatus(workspace.id, job.id, "paused"))}>
          Pause
        </GhostButton>
      )}
      {(job.status === "published" || job.status === "paused") && (
        <GhostButton
          disabled={busy}
          onClick={() => {
            if (confirm("Close this JD? This cannot be undone.")) {
              void run(() => employerApi.changeJobStatus(workspace.id, job.id, "closed"));
            }
          }}
        >
          Close
        </GhostButton>
      )}
    </div>
  );
}

/* ================================ Applications ================================ */

function ApplicationsTab({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [rows, setRows] = useState<ApplicationRow[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    employerApi.applications(workspace.id, jobId).then((res) => setRows(res.data)).catch(() => setRows([]));
  }, [workspace.id, jobId]);

  useEffect(load, [load]);

  if (rows === null) {
    return <div className="space-y-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-24" />)}</div>;
  }

  if (rows.length === 0) {
    return (
      <Tile accent={TRUST} className="py-12 text-center" hover={false}>
        <p className="font-display text-xl font-bold tracking-tight">No applications yet</p>
        <p className="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-white/60">
          Candidates can see this JD and apply free. Anyone who completes the job-specific interview
          arrives graded and ranked at the top of this list. Check the talent pool for matched
          candidates you can invite directly.
        </p>
      </Tile>
    );
  }

  return (
    <div className="space-y-3">
      {error && <p className="text-sm text-[#e05561]">{error}</p>}
      {rows.map((row, i) => (
        <ApplicationCard key={row.id} row={row} index={i} jobId={jobId} onChanged={load} onError={setError} />
      ))}
      <p className="pt-2 font-mono text-[10px] text-white/45">
        Graded applicants rank first by interview score. Contact details unlock at Shortlisted.
      </p>
    </div>
  );
}

function ApplicationCard({
  row, index, jobId, onChanged, onError,
}: {
  row: ApplicationRow; index: number; jobId: number; onChanged: () => void; onError: (m: string | null) => void;
}) {
  const { workspace } = useWorkspace();
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [interviews, setInterviews] = useState<InterviewRow[] | null>(null);
  const [busy, setBusy] = useState(false);

  const forward = nextStage(row.stage);
  const terminal = ["hired", "rejected", "withdrawn"].includes(row.stage);
  const strong = (row.mock_score ?? 0) >= 70;

  useEffect(() => {
    if (open && interviews === null) {
      employerApi.interviews(workspace.id, jobId, row.id)
        .then((res) => setInterviews(res.data))
        .catch(() => setInterviews([]));
    }
  }, [open, interviews, workspace.id, jobId, row.id]);

  async function move(stage: ApplicationStage, note?: string) {
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
    <motion.div
      initial={reduce ? false : { opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.55, ease: EASE, delay: Math.min(index, 6) * 0.05 }}
      className="overflow-hidden rounded-3xl border border-white/[0.08] bg-[#0a0f1c] shadow-[0_10px_40px_rgba(10,18,32,0.04)]"
    >
      <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between md:p-6">
        <button onClick={() => setOpen((o) => !o)} className="flex items-center gap-4 text-left" aria-expanded={open}>
          {row.is_graded && row.mock_score !== null ? (
            <Ring value={row.mock_score} size={62} stroke={8} color={strong ? TRUST : "#94a9c9"}>
              <span className="font-display text-sm font-bold">{row.mock_score}</span>
            </Ring>
          ) : (
            <div className="grid h-[62px] w-[62px] shrink-0 place-items-center rounded-full border border-dashed border-black/15">
              <span className="font-mono text-[8px] uppercase tracking-wider text-white/45">ungraded</span>
            </div>
          )}
          <div>
            <p className="font-display text-lg font-bold tracking-tight">{row.candidate?.name ?? "Candidate"}</p>
            <p className="mt-0.5 text-[12px] text-white/55">
              {row.candidate?.email ?? "Contact unlocks at Shortlisted"} · applied{" "}
              {new Date(row.applied_at).toLocaleDateString("en-IN", { day: "numeric", month: "short" })}
            </p>
          </div>
        </button>

        <div className="flex flex-wrap items-center gap-2">
          <Pill tone={terminal ? "neutral" : "trust"}>{STAGE_LABELS[row.stage]}</Pill>
          <Link
            href={`/employer/jobs/${jobId}/candidates/${row.id}`}
            className="rounded-full border border-white/[0.14] px-3.5 py-1.5 text-xs font-medium text-white/70 transition-colors hover:border-white/30 hover:text-white"
          >
            Full profile
          </Link>
          {!terminal && forward && (
            <PrimaryButton disabled={busy} onClick={() => void move(forward)} className="!px-4 !py-2 !text-xs">
              Advance to {STAGE_LABELS[forward]}
            </PrimaryButton>
          )}
          {!terminal && (
            <GhostButton disabled={busy} onClick={reject} className="!px-3.5 !py-1.5 !text-xs">
              Reject
            </GhostButton>
          )}
        </div>
      </div>

      {open && (
        <motion.div
          initial={reduce ? false : { opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.35, ease: EASE }}
          // Was a paper-white panel left over from the light theme, with
          // white text inside it — the detail was there and unreadable.
          className="border-t border-white/[0.07] bg-white/[0.02] px-5 py-5 md:px-6"
        >
          {row.rejection_reason && (
            <p className="mb-4 rounded-2xl border border-[#e0556155] bg-[#e055611f] px-4 py-3 text-sm text-[#f2a1a7]">
              Rejected: {row.rejection_reason}
            </p>
          )}
          <Label>Interview evidence</Label>
          {interviews === null ? (
            <Skeleton className="mt-3 h-24" />
          ) : interviews.length === 0 ? (
            <p className="mt-3 text-sm text-white/60">
              No interview rounds yet — advance this candidate to L1 to invite them.
            </p>
          ) : (
            <ul className="mt-3 space-y-3">
              {interviews.map((iv) => (
                <li key={iv.id} className="rounded-2xl border border-white/[0.08] bg-[#0a0f1c] p-5">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Pill tone="dark">{iv.round}</Pill>
                      <span className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/50">{iv.status}</span>
                    </div>
                    {iv.overall_score !== null && (
                      <span className="font-display text-2xl font-bold tracking-tight">{iv.overall_score}</span>
                    )}
                  </div>

                  {iv.grading_delayed && (
                    <p className="mt-2 text-xs text-white/60">
                      Grading is taking longer than usual. Scores appear when it completes — we never fabricate them.
                    </p>
                  )}

                  {iv.dimension_scores && (
                    <div className="mt-4 space-y-2.5">
                      {Object.entries(iv.dimension_scores).map(([key, value]) => (
                        <div key={key}>
                          <div className="flex items-baseline justify-between text-[11px]">
                            <span className="capitalize text-white/65">{key.replaceAll("_", " ")}</span>
                            <span className="font-mono font-semibold">{value}</span>
                          </div>
                          <div className="mt-1">
                            <SkillMeter pct={value} color={value >= 70 ? TRUST : "#94a9c9"} />
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {iv.grading_summary && (
                    <p className="mt-4 border-l-2 border-[#4d8ef7] pl-4 text-[13px] leading-relaxed text-white/70">
                      {iv.grading_summary}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </motion.div>
      )}
    </motion.div>
  );
}

/* ================================ Talent pool ================================ */

function TalentPoolTab({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [rows, setRows] = useState<TalentPoolCandidate[] | null>(null);
  const [invited, setInvited] = useState<number[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);

  useEffect(() => {
    setRows(null);
    employerApi.talentPool(workspace.id, jobId).then((res) => setRows(res.data)).catch(() => setRows([]));
  }, [workspace.id, jobId]);

  async function invite(candidateId: number) {
    setBusyId(candidateId);
    try {
      await employerApi.inviteStudent(workspace.id, jobId, candidateId);
      setInvited((prev) => [...prev, candidateId]);
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="space-y-5">
      <InkPanel glow={VIOLET}>
        <Label dark>Matched candidates</Label>
        <p className="font-display mt-2.5 max-w-2xl text-xl font-bold leading-tight tracking-tight md:text-2xl">
          Candidates who match this role,{" "}
          <span className="bg-gradient-to-r from-[#9d6bf5] to-[#4d8ef7] bg-clip-text text-transparent">
            with a CV on file and a scored history.
          </span>
        </p>
        <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/50">
          Matched on the skills your JD names, weighted by readiness and interview history.
          Inviting someone is a nudge — they still choose to apply and still sit your job-specific interview.
        </p>
      </InkPanel>

      {rows === null ? (
        <div className="space-y-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-32" />)}</div>
      ) : rows.length === 0 ? (
        <Tile accent={VIOLET} className="py-12 text-center" hover={false}>
          <p className="font-display text-xl font-bold tracking-tight">No matches yet</p>
          <p className="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-white/60">
            Nobody currently matches the skills on this JD, or everyone who matches has already
            applied. Add or broaden the skills on the JD to widen the pool.
          </p>
        </Tile>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 md:gap-5">
          {rows.map((c, i) => (
            <Tile key={c.candidate_id} accent={c.match_score >= 70 ? VIOLET : TRUST} index={i} hover={false}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="font-display text-lg font-bold tracking-tight">{c.name}</p>
                  {c.training && (
                    <p className="mt-1 text-[12px] text-white/60">
                      {c.training.course} ·{" "}
                      <span className={c.training.status === "completed" ? "text-[#0da06e]" : ""}>
                        {c.training.status}
                      </span>
                    </p>
                  )}
                </div>
                <Ring value={c.match_score} size={64} stroke={8} color={c.match_score >= 70 ? VIOLET : TRUST}>
                  <div className="text-center leading-none">
                    <span className="font-display block text-sm font-bold">{c.match_score}</span>
                    <span className="font-mono text-[7px] uppercase tracking-wider text-white/45">match</span>
                  </div>
                </Ring>
              </div>

              <div className="mt-4">
                <div className="flex items-baseline justify-between text-[11px]">
                  <span className="text-white/60">Skill coverage</span>
                  <span className="font-mono font-semibold">{c.skill_match_pct}%</span>
                </div>
                <div className="mt-1.5">
                  <SkillMeter pct={c.skill_match_pct} color={VIOLET} />
                </div>
              </div>

              <div className="mt-3 flex flex-wrap gap-1.5">
                {c.matched_skills.map((s) => (
                  <span key={s} className="rounded-full bg-[#e6f7ef] px-2.5 py-1 font-mono text-[10px] text-[#0da06e]">
                    {s}
                  </span>
                ))}
                {c.missing_skills.map((s) => (
                  <span key={s} className="rounded-full bg-black/[0.04] px-2.5 py-1 font-mono text-[10px] text-white/50 line-through">
                    {s}
                  </span>
                ))}
              </div>

              <div className="mt-5 flex items-end justify-between gap-4 border-t border-white/[0.07] pt-4">
                <div className="flex gap-5">
                  <div>
                    <p className="font-display text-lg font-bold leading-none">{c.readiness_index}</p>
                    <Label>Readiness</Label>
                  </div>
                  <div>
                    <p className="font-display text-lg font-bold leading-none">
                      {c.mock_average || "—"}
                    </p>
                    <Label>Mock avg · {c.mock_attempts}</Label>
                  </div>
                  {c.cv_ready && (
                    <div>
                      <p className="font-display text-lg font-bold leading-none text-[#0da06e]">✓</p>
                      <Label>CV ready</Label>
                    </div>
                  )}
                </div>
                {invited.includes(c.candidate_id) ? (
                  <Pill tone="verify">Invited</Pill>
                ) : (
                  <PrimaryButton
                    disabled={busyId === c.candidate_id}
                    onClick={() => void invite(c.candidate_id)}
                    className="!px-4 !py-2 !text-xs"
                  >
                    Invite to apply
                  </PrimaryButton>
                )}
              </div>
            </Tile>
          ))}
        </div>
      )}
    </div>
  );
}

/* ================================ JD mock ================================ */

function MockTab({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [mock, setMock] = useState<JdMockData | null | "none">(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    employerApi.mock(workspace.id, jobId).then((res) => setMock(res.data)).catch(() => setMock("none"));
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

  if (mock === null) return <Skeleton className="h-72" />;

  if (mock === "none") {
    return (
      <Tile accent={TRUST} hover={false} className="py-10 text-center">
        <p className="text-sm text-white/60">
          No interview built yet — it is generated automatically when the JD is published.
        </p>
      </Tile>
    );
  }

  return (
    <div className="space-y-4 md:space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-white/[0.08] bg-[#0a0f1c] px-6 py-4 shadow-[0_10px_40px_rgba(10,18,32,0.04)]">
        <p className="flex items-center gap-2.5 text-sm">
          <Pill tone="trust">v{mock.version}</Pill>
          <span className="font-mono text-[11px] uppercase tracking-[0.14em] text-white/55">{mock.status}</span>
          {mock.source === "fallback" && (
            <span className="text-xs text-white/55">template-based — regenerate for AI-tailored questions</span>
          )}
        </p>
        <GhostButton disabled={busy || mock.status === "pending"} onClick={() => void regenerate()}>
          {busy ? "Queuing…" : "Regenerate as new version"}
        </GhostButton>
      </div>
      {error && <p className="text-sm text-[#e05561]">{error}</p>}

      {mock.rubric && (
        <Tile accent={DEEP} hover={false}>
          <Label>Grading rubric</Label>
          <p className="font-display mt-1 text-lg font-bold tracking-tight">How every answer is scored</p>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {mock.rubric.dimensions.map((d, i) => (
              <motion.div
                key={d.key}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: EASE, delay: i * 0.06 }}
                className="rounded-2xl border border-white/[0.08] bg-white/[0.02] p-4"
              >
                <div className="flex items-baseline justify-between">
                  <p className="text-sm font-semibold">{d.label}</p>
                  <p className="font-display text-lg font-bold text-[#4d8ef7]">{d.weight}%</p>
                </div>
                <div className="mt-2"><SkillMeter pct={d.weight} color={DEEP} /></div>
                <p className="mt-2.5 text-[12px] leading-relaxed text-white/60">{d.criteria}</p>
              </motion.div>
            ))}
          </div>
        </Tile>
      )}

      {mock.questions && (
        <Tile accent={TRUST} hover={false}>
          <Label>Asked of every applicant · {mock.questions.length} questions</Label>
          <p className="font-display mt-1 text-lg font-bold tracking-tight">The interview itself</p>
          <ol className="mt-5 space-y-4">
            {mock.questions.map((q, i) => (
              <motion.li
                key={q.id}
                initial={{ opacity: 0, x: -8 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.5, ease: EASE, delay: Math.min(i, 8) * 0.04 }}
                className="flex gap-4"
              >
                <span className="font-display shrink-0 text-2xl font-bold leading-none text-black/[0.13]">
                  {String(q.id).padStart(2, "0")}
                </span>
                <div>
                  <p className="text-[15px] leading-snug">{q.text}</p>
                  <p className="mt-1.5 flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-[0.12em] text-white/45">
                    <span>{q.skill}</span>
                    <span>·</span>
                    <span>{q.type}</span>
                    {q.weight > 1 && <Pill tone="amber">critical</Pill>}
                  </p>
                </div>
              </motion.li>
            ))}
          </ol>
        </Tile>
      )}
    </div>
  );
}

/* ================================ Automation ================================ */

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

  const select = "rounded-full border border-black/[0.1] bg-[#0a0f1c] px-3.5 py-2 text-sm";

  return (
    <div className="space-y-5">
      <InkPanel glow={TRUST}>
        <Label dark>Run it without you</Label>
        <p className="font-display mt-2.5 max-w-2xl text-xl font-bold leading-tight tracking-tight md:text-2xl">
          Set a bar once.{" "}
          <span className="bg-gradient-to-r from-[#4d8ef7] to-[#9d6bf5] bg-clip-text text-transparent">
            The pipeline moves itself.
          </span>
        </p>
        <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/50">
          Automation can advance or park candidates. It can never reject anyone and never releases an
          offer — those stay human decisions. Every automated move is labelled in the candidate&apos;s timeline.
        </p>
      </InkPanel>

      <Tile accent={TRUST} hover={false}>
        <Label>New rule</Label>
        <form onSubmit={create} className="mt-4 flex flex-wrap items-center gap-2.5 text-sm">
          <span className="text-white/65">When</span>
          <select value={trigger} onChange={(e) => setTrigger(e.target.value as typeof trigger)} className={select}>
            <option value="application_graded">the job interview is graded</option>
            <option value="interview_graded">a follow-up round is graded</option>
          </select>
          {trigger === "interview_graded" && (
            <select value={round} onChange={(e) => setRound(e.target.value as typeof round)} className={select}>
              <option value="l1">L1</option>
              <option value="l2">L2</option>
            </select>
          )}
          <span className="text-white/65">at</span>
          <input
            type="number" min={0} max={100} value={minScore}
            onChange={(e) => setMinScore(Number(e.target.value))}
            className="w-20 rounded-full border border-black/[0.1] bg-[#0a0f1c] px-3.5 py-2 text-center font-mono text-sm"
          />
          <span className="text-white/65">% or above, advance to</span>
          <select value={targetStage} onChange={(e) => setTargetStage(e.target.value)} className={select}>
            <option value="shortlisted">Shortlisted</option>
            <option value="l1">L1</option>
            <option value="l2">L2</option>
          </select>
          <PrimaryButton type="submit" disabled={busy}>Add rule</PrimaryButton>
        </form>
        {error && <p className="mt-3 text-sm text-[#e05561]">{error}</p>}
      </Tile>

      {rules === null ? (
        <Skeleton className="h-24" />
      ) : rules.length === 0 ? (
        <Tile accent={TRUST} hover={false} className="py-10 text-center">
          <p className="text-sm text-white/60">
            No rules yet. Add one above — “interview ≥ 70% → auto-shortlist” — and this JD runs itself.
          </p>
        </Tile>
      ) : (
        <ul className="space-y-3">
          {rules.map((rule, i) => (
            <motion.li
              key={rule.id}
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, ease: EASE, delay: i * 0.05 }}
              className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-white/[0.08] bg-[#0a0f1c] px-6 py-4 shadow-[0_10px_40px_rgba(10,18,32,0.04)]"
            >
              <p className="text-sm">
                <span className="font-semibold">
                  {rule.trigger === "application_graded" ? "Job interview" : `Round ${rule.round?.toUpperCase()}`}
                </span>{" "}
                ≥ <span className="font-mono font-semibold text-[#4d8ef7]">{rule.min_score}%</span> →{" "}
                {rule.action === "advance" ? `advance to ${rule.target_stage}` : "park for review"}
                {typeof rule.runs_count === "number" && (
                  <span className="ml-3 font-mono text-[11px] text-white/45">{rule.runs_count} runs</span>
                )}
              </p>
              <button
                onClick={() => employerApi.toggleRule(workspace.id, jobId, rule.id).then(load)}
                className={`rounded-full px-3.5 py-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.12em] transition-colors ${
                  rule.enabled ? "bg-[#e6f7ef] text-[#0da06e]" : "bg-white/[0.06] text-white/55"
                }`}
              >
                {rule.enabled ? "On" : "Off"}
              </button>
            </motion.li>
          ))}
        </ul>
      )}
    </div>
  );
}
