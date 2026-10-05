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
  type TalentPoolCandidateCv,
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
  // Read once on first render, not via useSearchParams — this page is
  // already client-only (params is a Promise), and a query-param nudge
  // right after "Post it live" doesn't need App Router's Suspense machinery.
  const [tab, setTab] = useState<Tab>(() => {
    if (typeof window === "undefined") return "applications";
    const requested = new URLSearchParams(window.location.search).get("tab");
    return TABS.some((t) => t.id === requested) ? (requested as Tab) : "applications";
  });
  const [missing, setMissing] = useState(false);

  const loadJob = useCallback(() => {
    employerApi.job(workspace.id, jobId).then((res) => setJob(res.data)).catch(() => setMissing(true));
  }, [workspace.id, jobId]);

  useEffect(loadJob, [loadJob]);

  if (missing) {
    return (
      <p className="rounded-3xl border border-[var(--bj-dash-border)] bg-white p-8 text-sm text-[var(--bj-dash-muted)]">
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
                ? "bg-[var(--bj-dash-primary)] text-white shadow-[0_8px_20px_-8px_rgba(26,96,72,0.55)]"
                : "border border-[var(--bj-dash-border)] bg-white text-[var(--bj-dash-muted)] hover:border-[var(--bj-dash-primary)]/40 hover:text-[var(--bj-dash-primary)]"
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
        <p className="bj-dash-serif text-xl tracking-tight">No applications yet</p>
        <p className="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-[var(--bj-dash-muted)]">
          Candidates can see this JD and apply free. Anyone who completes the job-specific interview
          arrives graded and ranked at the top of this list. Check the talent pool for matched
          candidates you can invite directly.
        </p>
      </Tile>
    );
  }

  return (
    <div className="space-y-3">
      {error && <p className="text-sm text-[var(--bj-dash-score-below)]">{error}</p>}
      {rows.map((row, i) => (
        <ApplicationCard key={row.id} row={row} index={i} jobId={jobId} onChanged={load} onError={setError} />
      ))}
      <p className="pt-2 font-mono text-[10px] text-[var(--bj-dash-muted)]">
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
      className="overflow-hidden rounded-3xl border border-[var(--bj-dash-border)] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_10px_28px_-18px_rgba(0,0,0,0.18)]"
    >
      <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between md:p-6">
        <button onClick={() => setOpen((o) => !o)} className="flex items-center gap-4 text-left" aria-expanded={open}>
          {row.is_graded && row.mock_score !== null ? (
            <Ring value={row.mock_score} size={62} stroke={8} color={strong ? TRUST : "var(--bj-dash-muted)"}>
              <span className="font-display text-sm font-bold">{row.mock_score}</span>
            </Ring>
          ) : (
            <div className="grid h-[62px] w-[62px] shrink-0 place-items-center rounded-full border border-dashed border-black/15">
              <span className="font-mono text-[8px] uppercase tracking-wider text-[var(--bj-dash-muted)]">ungraded</span>
            </div>
          )}
          <div>
            <p className="font-display text-lg font-bold tracking-tight">{row.candidate?.name ?? "Candidate"}</p>
            <p className="mt-0.5 text-[12px] text-[var(--bj-dash-muted)]">
              {row.candidate?.email ?? "Contact unlocks at Shortlisted"} · applied{" "}
              {new Date(row.applied_at).toLocaleDateString("en-IN", { day: "numeric", month: "short" })}
            </p>
          </div>
        </button>

        <div className="flex flex-wrap items-center gap-2">
          <Pill tone={terminal ? "neutral" : "trust"}>{STAGE_LABELS[row.stage]}</Pill>
          <Link
            href={`/employer/jobs/${jobId}/candidates/${row.id}`}
            className="rounded-full border border-[var(--bj-dash-border)] px-3.5 py-1.5 text-xs font-medium text-[var(--bj-dash-muted)] transition-colors hover:border-[var(--bj-dash-primary)]/40 hover:text-[var(--bj-dash-primary)]"
          >
            Full profile
          </Link>
          {row.evidence?.recording_url && (
            <a
              href={row.evidence.recording_url}
              target="_blank"
              rel="noopener noreferrer"
              className="rounded-full border border-[var(--bj-dash-border)] px-3.5 py-1.5 text-xs font-medium text-[var(--bj-dash-muted)] transition-colors hover:border-[var(--bj-dash-primary)]/40 hover:text-[var(--bj-dash-primary)]"
            >
              ▶ Recording
            </a>
          )}
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
          className="border-t border-[var(--bj-dash-border)] bg-[var(--bj-dash-canvas)] px-5 py-5 md:px-6"
        >
          {row.rejection_reason && (
            <p className="mb-4 rounded-2xl border border-[var(--bj-dash-score-below-bg)] bg-[var(--bj-dash-score-below-bg)] px-4 py-3 text-sm text-[var(--bj-dash-score-below)]">
              Rejected: {row.rejection_reason}
            </p>
          )}
          <Label>Interview evidence</Label>
          {interviews === null ? (
            <Skeleton className="mt-3 h-24" />
          ) : interviews.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--bj-dash-muted)]">
              No interview rounds yet — advance this candidate to L1 to invite them.
            </p>
          ) : (
            <ul className="mt-3 space-y-3">
              {interviews.map((iv) => (
                <li key={iv.id} className="rounded-2xl border border-[var(--bj-dash-border)] bg-[var(--bj-dash-canvas)] p-5">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Pill tone="dark">{iv.round}</Pill>
                      <span className="font-mono text-[10px] uppercase tracking-[0.14em] text-[var(--bj-dash-muted)]">{iv.status}</span>
                    </div>
                    {iv.overall_score !== null && (
                      <span className="font-display text-2xl font-bold tracking-tight">{iv.overall_score}</span>
                    )}
                  </div>

                  {iv.grading_delayed && (
                    <p className="mt-2 text-xs text-[var(--bj-dash-muted)]">
                      Grading is taking longer than usual. Scores appear when it completes — we never fabricate them.
                    </p>
                  )}

                  {iv.dimension_scores && (
                    <div className="mt-4 space-y-2.5">
                      {Object.entries(iv.dimension_scores).map(([key, value]) => (
                        <div key={key}>
                          <div className="flex items-baseline justify-between text-[11px]">
                            <span className="capitalize text-[var(--bj-dash-muted)]">{key.replaceAll("_", " ")}</span>
                            <span className="font-mono font-semibold">{value}</span>
                          </div>
                          <div className="mt-1">
                            <SkillMeter pct={value} color={value >= 70 ? TRUST : "var(--bj-dash-muted)"} />
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {iv.grading_summary && (
                    <p className="mt-4 border-l-2 border-[var(--bj-dash-primary)] pl-4 text-[13px] leading-relaxed text-[var(--bj-dash-muted)]">
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
  const [shortlisted, setShortlisted] = useState<number[]>([]);
  const [shortlistBusyId, setShortlistBusyId] = useState<number | null>(null);
  // Which candidate's CV is expanded inline right now, and what it holds
  // once fetched — null once fetched-but-empty is a real state (no CV on
  // file), distinct from "not fetched yet" (key absent).
  const [openCvId, setOpenCvId] = useState<number | null>(null);
  const [cvById, setCvById] = useState<Record<number, TalentPoolCandidateCv | null>>({});
  const [recordingBusyId, setRecordingBusyId] = useState<number | null>(null);
  const [recordingError, setRecordingError] = useState<number | null>(null);
  // Off by default on every visit, on purpose — never persisted, never
  // silently on. Merges in synthetic candidates for a demo before real
  // candidate volume exists; a real employer never sees this unless they
  // click it themselves, for this one page load.
  // Sticks on this browser once turned on — every job's Talent Pool tab,
  // every new tab, even tomorrow — until turned off again here. localStorage
  // rather than sessionStorage specifically so a fresh tab or a restarted
  // browser doesn't quietly reset it back to off mid-demo. Still scoped to
  // this one browser profile, not a server-side default: anyone else's
  // browser — a real employer's, or this same account from a different
  // machine — starts at off regardless.
  const [sampleMode, setSampleModeState] = useState(() => {
    if (typeof window === "undefined") return false;
    return localStorage.getItem("talentpool_sample_mode") === "1";
  });
  const setSampleMode = useCallback((next: boolean | ((prev: boolean) => boolean)) => {
    setSampleModeState((prev) => {
      const value = typeof next === "function" ? next(prev) : next;
      try {
        localStorage.setItem("talentpool_sample_mode", value ? "1" : "0");
      } catch {
        // Private browsing or storage disabled — the toggle still works for this page, just won't carry over.
      }
      return value;
    });
  }, []);

  useEffect(() => {
    setRows(null);
    employerApi.talentPool(workspace.id, jobId, sampleMode).then((res) => setRows(res.data)).catch(() => setRows([]));
  }, [workspace.id, jobId, sampleMode]);

  async function invite(candidateId: number) {
    setBusyId(candidateId);
    try {
      await employerApi.inviteStudent(workspace.id, jobId, candidateId);
      setInvited((prev) => [...prev, candidateId]);
    } finally {
      setBusyId(null);
    }
  }

  /** Straight from the pool — no invite/apply step. Tells the candidate over WhatsApp they're already picked. */
  async function shortlist(candidate: TalentPoolCandidate) {
    // A sample candidate isn't a real person — there's no real
    // application to record and no real phone to message, so this stays
    // client-side only: just enough to show what the click does without
    // hitting an endpoint that has nothing real to act on.
    if (candidate.is_sample) {
      setShortlisted((prev) => [...prev, candidate.candidate_id]);
      return;
    }

    setShortlistBusyId(candidate.candidate_id);
    try {
      await employerApi.shortlistCandidate(workspace.id, jobId, candidate.candidate_id);
      setShortlisted((prev) => [...prev, candidate.candidate_id]);
    } finally {
      setShortlistBusyId(null);
    }
  }

  async function toggleCv(candidate: TalentPoolCandidate) {
    const candidateId = candidate.candidate_id;
    if (openCvId === candidateId) {
      setOpenCvId(null);
      return;
    }
    setOpenCvId(candidateId);
    if (candidate.is_sample) {
      // Already have it inline — no real CvProfile exists to fetch for a
      // synthetic candidate_id.
      setCvById((prev) => ({
        ...prev,
        [candidateId]: {
          summary: candidate.cv_summary ?? null,
          skills: candidate.matched_skills.concat(candidate.missing_skills),
          experience: candidate.education ? [{ title: candidate.education }] : [],
          education: candidate.education ? [{ name: candidate.education }] : [],
        },
      }));
      return;
    }
    if (!(candidateId in cvById)) {
      try {
        const r = await employerApi.talentPoolCv(workspace.id, jobId, candidateId);
        setCvById((prev) => ({ ...prev, [candidateId]: r.data }));
      } catch {
        setCvById((prev) => ({ ...prev, [candidateId]: null }));
      }
    }
  }

  async function viewRecording(candidateId: number) {
    setRecordingBusyId(candidateId);
    setRecordingError(null);
    try {
      const r = await employerApi.talentPoolRecording(workspace.id, jobId, candidateId);
      window.open(r.data.url, "_blank", "noopener,noreferrer");
    } catch {
      setRecordingError(candidateId);
    } finally {
      setRecordingBusyId(null);
    }
  }

  return (
    <div className="space-y-5">
      <InkPanel glow={VIOLET}>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <Label dark>Matched candidates</Label>
            <p className="bj-dash-serif mt-2.5 max-w-2xl text-xl leading-tight tracking-tight md:text-2xl">
              Candidates who match this role,{" "}
              <span className="bg-gradient-to-r from-[var(--bj-dash-focus)] to-[var(--bj-dash-primary)] bg-clip-text text-transparent">
                with a CV on file and a scored history.
              </span>
            </p>
            <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/50">
              Matched on the skills your JD names, weighted by readiness and interview history.
              Inviting someone is a nudge — they still choose to apply and still sit your job-specific interview.
            </p>
          </div>
          <button
            type="button"
            onClick={() => setSampleMode((v) => !v)}
            title="Adds synthetic candidates for this view only — never saved, never real, off again next visit unless you click this again."
            className={`shrink-0 rounded-full border px-4 py-2 text-xs font-semibold transition ${
              sampleMode ? "border-amber-400 bg-amber-400/20 text-amber-200" : "border-white/20 text-white/70 hover:border-white/40"
            }`}
          >
            {sampleMode ? "◉ Previewing with sample data" : "Preview with sample data"}
          </button>
        </div>
      </InkPanel>

      {rows === null ? (
        <div className="space-y-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-32" />)}</div>
      ) : rows.length === 0 ? (
        <Tile accent={VIOLET} className="py-12 text-center" hover={false}>
          <p className="bj-dash-serif text-xl tracking-tight">No matches yet</p>
          <p className="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-[var(--bj-dash-muted)]">
            A student needs a matching CV skill, a role/course connected to this JD, and a completed
            AI Readiness Interview to show up here — or everyone who qualifies has already applied.
            This pool fills in as more students complete that interview.
          </p>
        </Tile>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 md:gap-5">
          {rows.map((c, i) => (
            <Tile key={c.candidate_id} accent={c.match_score >= 70 ? VIOLET : TRUST} index={i} hover={false}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <div className="flex flex-wrap items-center gap-1.5">
                    <p className="font-display text-lg font-bold tracking-tight">{c.name}</p>
                    {c.bgb_verified && (
                      <span
                        title="Label only for now — not tied to a real check yet."
                        className="rounded-full bg-[var(--bj-dash-score-strong-bg)] px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-wider text-[var(--bj-dash-score-strong)]"
                      >
                        ✓ BGB Verified
                      </span>
                    )}
                    {c.is_sample && (
                      <span className="rounded-full bg-amber-100 px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-wider text-amber-700">
                        Sample
                      </span>
                    )}
                  </div>
                  {c.is_sample ? (
                    <p className="mt-1 text-[12px] text-[var(--bj-dash-muted)]">
                      {c.location} · {c.education}
                    </p>
                  ) : (
                    c.training && (
                      <p className="mt-1 text-[12px] text-[var(--bj-dash-muted)]">
                        {c.training.course} ·{" "}
                        <span className={c.training.status === "completed" ? "text-[var(--bj-dash-score-strong)]" : ""}>
                          {c.training.status}
                        </span>
                      </p>
                    )
                  )}
                </div>
                <Ring value={c.match_score} size={64} stroke={8} color={c.match_score >= 70 ? VIOLET : TRUST}>
                  <div className="text-center leading-none">
                    <span className="font-display block text-sm font-bold">{c.match_score}</span>
                    <span className="font-mono text-[7px] uppercase tracking-wider text-[var(--bj-dash-muted)]">match</span>
                  </div>
                </Ring>
              </div>

              <div className="mt-4">
                <div className="flex items-baseline justify-between text-[11px]">
                  <span className="text-[var(--bj-dash-muted)]">Skill coverage</span>
                  <span className="font-mono font-semibold">{c.skill_match_pct}%</span>
                </div>
                <div className="mt-1.5">
                  <SkillMeter pct={c.skill_match_pct} color={VIOLET} />
                </div>
              </div>

              <div className="mt-3 flex flex-wrap gap-1.5">
                {c.matched_skills.map((s) => (
                  <span key={s} className="rounded-full bg-[var(--bj-dash-score-strong-bg)] px-2.5 py-1 font-mono text-[10px] text-[var(--bj-dash-score-strong)]">
                    {s}
                  </span>
                ))}
                {c.missing_skills.map((s) => (
                  <span key={s} className="rounded-full bg-[var(--bj-dash-soft)] px-2.5 py-1 font-mono text-[10px] text-[var(--bj-dash-muted)] line-through">
                    {s}
                  </span>
                ))}
              </div>

              <div className="mt-3 flex items-center gap-4">
                <button
                  type="button"
                  onClick={() => void toggleCv(c)}
                  className="text-[12px] font-semibold text-[var(--bj-dash-primary)]"
                >
                  {openCvId === c.candidate_id ? "Hide CV" : "View CV"}
                </button>
                {!c.is_sample && (
                  <button
                    type="button"
                    onClick={() => void viewRecording(c.candidate_id)}
                    disabled={recordingBusyId === c.candidate_id}
                    className="text-[12px] font-semibold text-[var(--bj-dash-primary)] disabled:opacity-50"
                  >
                    {recordingBusyId === c.candidate_id ? "Opening…" : "▶ View AI interview recording"}
                  </button>
                )}
              </div>
              {c.is_sample && (
                <p className="mt-1 text-[11px] text-[var(--bj-dash-muted)]">
                  {c.email} · {c.phone}
                </p>
              )}
              {recordingError === c.candidate_id && (
                <p className="mt-1 text-[11px] text-[var(--bj-dash-score-below)]">No recording available for this candidate.</p>
              )}

              {openCvId === c.candidate_id && (
                <div className="mt-3 rounded-[10px] border border-[var(--bj-dash-border)] bg-[var(--bj-dash-canvas)] p-3">
                  {!(c.candidate_id in cvById) ? (
                    <p className="text-[12px] text-[var(--bj-dash-muted)]">Loading…</p>
                  ) : cvById[c.candidate_id] === null ? (
                    <p className="text-[12px] text-[var(--bj-dash-muted)]">No CV on file.</p>
                  ) : (
                    (() => {
                      const cv = cvById[c.candidate_id] as TalentPoolCandidateCv;
                      return (
                        <div className="space-y-3 text-[12px]">
                          {cv.summary && <p className="leading-relaxed text-[var(--bj-dash-muted)]">{cv.summary}</p>}
                          {cv.skills.length > 0 && (
                            <div>
                              <p className="font-semibold text-[var(--bj-dash-muted)]">Skills</p>
                              <p className="mt-1 text-[var(--bj-dash-muted)]">{cv.skills.join(", ")}</p>
                            </div>
                          )}
                          {cv.experience.length > 0 && (
                            <div>
                              <p className="font-semibold text-[var(--bj-dash-muted)]">Experience</p>
                              {cv.experience.map((e, idx) => (
                                <p key={idx} className="mt-1 text-[var(--bj-dash-muted)]">
                                  {e.title}
                                  {e.company ? ` · ${e.company}` : ""}
                                  {e.period ? ` · ${e.period}` : ""}
                                </p>
                              ))}
                            </div>
                          )}
                          {cv.education.length > 0 && (
                            <div>
                              <p className="font-semibold text-[var(--bj-dash-muted)]">Education</p>
                              {cv.education.map((e, idx) => (
                                <p key={idx} className="mt-1 text-[var(--bj-dash-muted)]">{e.name}{e.detail ? ` · ${e.detail}` : ""}</p>
                              ))}
                            </div>
                          )}
                          {!cv.summary && cv.skills.length === 0 && cv.experience.length === 0 && cv.education.length === 0 && (
                            <p className="text-[var(--bj-dash-muted)]">Their CV is on file but doesn&apos;t have much filled in yet.</p>
                          )}
                        </div>
                      );
                    })()
                  )}
                </div>
              )}

              <div className="mt-5 flex items-end justify-between gap-4 border-t border-[var(--bj-dash-border)] pt-4">
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
                      <p className="font-display text-lg font-bold leading-none text-[var(--bj-dash-score-strong)]">✓</p>
                      <Label>CV ready</Label>
                    </div>
                  )}
                  {c.cv_mock_score !== null && (
                    <div>
                      <p className="font-display text-lg font-bold leading-none text-[var(--bj-dash-primary)]">{c.cv_mock_score}</p>
                      <Label>AI readiness</Label>
                    </div>
                  )}
                </div>
                {c.is_sample ? (
                  <div className="flex items-center gap-2">
                    {shortlisted.includes(c.candidate_id) ? (
                      <Pill tone="verify">✓ Shortlisted</Pill>
                    ) : (
                      <button
                        type="button"
                        onClick={() => void shortlist(c)}
                        title="Sample candidate — shows what the click does, no real message is sent."
                        className="rounded-full border border-[var(--bj-dash-primary)] px-4 py-2 text-xs font-semibold text-[var(--bj-dash-primary)] transition"
                      >
                        Shortlist
                      </button>
                    )}
                    <span className="text-[11px] italic text-[var(--bj-dash-muted)]">Demo only — not a real applicant</span>
                  </div>
                ) : (
                  <div className="flex items-center gap-2">
                    {shortlisted.includes(c.candidate_id) ? (
                      <Pill tone="verify">✓ Shortlisted</Pill>
                    ) : (
                      <button
                        type="button"
                        disabled={shortlistBusyId === c.candidate_id}
                        onClick={() => void shortlist(c)}
                        title="Marks them shortlisted and sends a WhatsApp message letting them know — no application needed first."
                        className="rounded-full border border-[var(--bj-dash-primary)] px-4 py-2 text-xs font-semibold text-[var(--bj-dash-primary)] transition disabled:opacity-50"
                      >
                        {shortlistBusyId === c.candidate_id ? "Shortlisting…" : "Shortlist"}
                      </button>
                    )}
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
        <p className="text-sm text-[var(--bj-dash-muted)]">
          No interview built yet — it is generated automatically when the JD is published.
        </p>
      </Tile>
    );
  }

  return (
    <div className="space-y-4 md:space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-[var(--bj-dash-border)] bg-[var(--bj-dash-canvas)] px-6 py-4 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_10px_28px_-18px_rgba(0,0,0,0.18)]">
        <p className="flex items-center gap-2.5 text-sm">
          <Pill tone="trust">v{mock.version}</Pill>
          <span className="font-mono text-[11px] uppercase tracking-[0.14em] text-[var(--bj-dash-muted)]">{mock.status}</span>
          {mock.source === "fallback" && (
            <span className="text-xs text-[var(--bj-dash-muted)]">template-based — regenerate for AI-tailored questions</span>
          )}
        </p>
        <GhostButton disabled={busy || mock.status === "pending"} onClick={() => void regenerate()}>
          {busy ? "Queuing…" : "Regenerate as new version"}
        </GhostButton>
      </div>
      {error && <p className="text-sm text-[var(--bj-dash-score-below)]">{error}</p>}

      {mock.rubric && (
        <Tile accent={DEEP} hover={false}>
          <Label>Grading rubric</Label>
          <p className="bj-dash-serif mt-1 text-lg tracking-tight">How every answer is scored</p>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {mock.rubric.dimensions.map((d, i) => (
              <motion.div
                key={d.key}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: EASE, delay: i * 0.06 }}
                className="rounded-2xl border border-[var(--bj-dash-border)] bg-[var(--bj-dash-canvas)] p-4"
              >
                <div className="flex items-baseline justify-between">
                  <p className="text-sm font-semibold">{d.label}</p>
                  <p className="font-display text-lg font-bold text-[var(--bj-dash-primary)]">{d.weight}%</p>
                </div>
                <div className="mt-2"><SkillMeter pct={d.weight} color={DEEP} /></div>
                <p className="mt-2.5 text-[12px] leading-relaxed text-[var(--bj-dash-muted)]">{d.criteria}</p>
              </motion.div>
            ))}
          </div>
        </Tile>
      )}

      {mock.questions && (
        <Tile accent={TRUST} hover={false}>
          <Label>Asked of every applicant · {mock.questions.length} questions</Label>
          <p className="bj-dash-serif mt-1 text-lg tracking-tight">The interview itself</p>
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
                  <p className="mt-1.5 flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-[0.12em] text-[var(--bj-dash-muted)]">
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

  const select = "rounded-full border border-[var(--bj-dash-border)] bg-white px-3.5 py-2 text-sm text-[var(--bj-dash-ink)] outline-none focus:border-[var(--bj-dash-primary)] focus:ring-4 focus:ring-[var(--bj-dash-primary)]/15";

  return (
    <div className="space-y-5">
      <InkPanel glow={TRUST}>
        <Label dark>Run it without you</Label>
        <p className="bj-dash-serif mt-2.5 max-w-2xl text-xl leading-tight tracking-tight md:text-2xl">
          Set a bar once.{" "}
          <span className="bg-gradient-to-r from-[var(--bj-dash-primary)] to-[var(--bj-dash-focus)] bg-clip-text text-transparent">
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
          <span className="text-[var(--bj-dash-muted)]">When</span>
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
          <span className="text-[var(--bj-dash-muted)]">at</span>
          <input
            type="number" min={0} max={100} value={minScore}
            onChange={(e) => setMinScore(Number(e.target.value))}
            className="w-20 rounded-full border border-[var(--bj-dash-border)] bg-white px-3.5 py-2 text-center font-mono text-sm text-[var(--bj-dash-ink)] outline-none focus:border-[var(--bj-dash-primary)] focus:ring-4 focus:ring-[var(--bj-dash-primary)]/15"
          />
          <span className="text-[var(--bj-dash-muted)]">% or above, advance to</span>
          <select value={targetStage} onChange={(e) => setTargetStage(e.target.value)} className={select}>
            <option value="shortlisted">Shortlisted</option>
            <option value="l1">L1</option>
            <option value="l2">L2</option>
          </select>
          <PrimaryButton type="submit" disabled={busy}>Add rule</PrimaryButton>
        </form>
        {error && <p className="mt-3 text-sm text-[var(--bj-dash-score-below)]">{error}</p>}
      </Tile>

      {rules === null ? (
        <Skeleton className="h-24" />
      ) : rules.length === 0 ? (
        <Tile accent={TRUST} hover={false} className="py-10 text-center">
          <p className="text-sm text-[var(--bj-dash-muted)]">
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
              className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-[var(--bj-dash-border)] bg-white px-6 py-4 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_10px_28px_-18px_rgba(0,0,0,0.18)]"
            >
              <p className="text-sm">
                <span className="font-semibold">
                  {rule.trigger === "application_graded" ? "Job interview" : `Round ${rule.round?.toUpperCase()}`}
                </span>{" "}
                ≥ <span className="font-mono font-semibold text-[var(--bj-dash-primary)]">{rule.min_score}%</span> →{" "}
                {rule.action === "advance" ? `advance to ${rule.target_stage}` : "park for review"}
                {typeof rule.runs_count === "number" && (
                  <span className="ml-3 font-mono text-[11px] text-[var(--bj-dash-muted)]">{rule.runs_count} runs</span>
                )}
              </p>
              <button
                onClick={() => employerApi.toggleRule(workspace.id, jobId, rule.id).then(load)}
                className={`rounded-full px-3.5 py-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.12em] transition-colors ${
                  rule.enabled ? "bg-[var(--bj-dash-score-strong-bg)] text-[var(--bj-dash-score-strong)]" : "bg-[var(--bj-dash-soft)] text-[var(--bj-dash-muted)]"
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
