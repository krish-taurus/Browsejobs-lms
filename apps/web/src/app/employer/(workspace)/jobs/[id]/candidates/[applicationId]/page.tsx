"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { useParams } from "next/navigation";
import { motion, useReducedMotion } from "framer-motion";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { EASE, Label, Pill, Skeleton, Tile } from "@/components/employer/ui";
import { AMBER, Ring, SkillMeter, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
import {
  employerApi,
  STAGE_LABELS,
  type ApplicationStage,
  type CandidateProfileData,
  type JobRound,
} from "@/lib/employer";

/**
 * The full candidate view an employer opens from the pipeline (PRD-E F17).
 *
 * Three things this screen refuses to do, because each of them would be a
 * quiet lie about a real person:
 *
 *  - It never shows contact details before Shortlisted. The API withholds
 *    them, and the panel says why rather than rendering an empty field.
 *  - Verification renders the *outcome* only. There is no document, no
 *    reference number and no provider payload in the response to leak.
 *  - The session panel states that proctoring was not captured. Camera and
 *    window-switch signals are not recorded yet, and an absent warning
 *    would read as a clean session we never actually observed.
 */

const VERIFY_TONE: Record<string, { color: string; bg: string; border: string }> = {
  verified: { color: "#5fd6a6", bg: "#0da06e1f", border: "#0da06e55" },
  pending: { color: "#e8bf63", bg: "#b07c001f", border: "#b07c0055" },
  failed: { color: "#f2a1a7", bg: "#e055611f", border: "#e0556155" },
  expired: { color: "#f2a1a7", bg: "#e055611f", border: "#e0556155" },
  not_started: { color: "rgba(244,247,251,0.5)", bg: "rgba(255,255,255,0.04)", border: "rgba(255,255,255,0.10)" },
};

function tone(status: string) {
  return VERIFY_TONE[status] ?? VERIFY_TONE.not_started;
}

/**
 * The line under a check. "No confirmation on record" was true of every
 * unverified state, which made a check that came back *failed* read the same
 * as one nobody has started — the two mean very different things to someone
 * deciding whether to interview.
 */
function checkSubtitle(status: string, verifiedAt: string | null): string {
  if (verifiedAt) return `Confirmed ${shortDate(verifiedAt)}`;

  switch (status) {
    case "pending":
      return "Submitted — BrowseJobs is checking it";
    case "failed":
      return "Checked, and it did not confirm";
    case "expired":
      return "Confirmed once, now out of date";
    default:
      return "Nothing submitted yet";
  }
}

function minutes(seconds: number | null): string | null {
  if (seconds === null || seconds <= 0) return null;
  return `${Math.round(seconds / 60)} min`;
}

function shortDate(iso: string | null): string | null {
  if (!iso) return null;
  return new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
}

export default function CandidateProfilePage() {
  const params = useParams<{ id: string; applicationId: string }>();
  const jobId = Number(params.id);
  const applicationId = Number(params.applicationId);
  const { workspace } = useWorkspace();
  const reduce = useReducedMotion();

  const [data, setData] = useState<CandidateProfileData | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setData(null);
    setFailed(false);
    employerApi
      .candidateProfile(workspace.id, jobId, applicationId)
      .then((r) => setData(r.data))
      .catch(() => setFailed(true));
  }, [workspace.id, jobId, applicationId]);

  if (failed) {
    return (
      <Tile accent={TRUST} hover={false}>
        <p className="text-sm text-white/60">This candidate could not be loaded.</p>
        <div className="mt-4">
          <Link
            href={`/employer/jobs/${jobId}`}
            className="inline-flex items-center gap-1.5 rounded-full border border-white/[0.14] bg-white/[0.04] px-4 py-2 text-sm font-medium text-white/80 transition-colors hover:border-white/30"
          >
            Back to the JD
          </Link>
        </div>
      </Tile>
    );
  }

  if (!data) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-40" />
        <Skeleton className="h-52" />
        <Skeleton className="h-52" />
      </div>
    );
  }

  const { application, candidate, cv, verification, interview } = data;
  const score = application.mock_score;

  return (
    <div className="w-full min-w-0 space-y-5 pb-12">
      <Link
        href={`/employer/jobs/${jobId}`}
        className="inline-flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-white/45 transition-colors hover:text-white"
      >
        ← Back to the JD
      </Link>

      {/* Identity ------------------------------------------------------ */}
      <motion.div
        initial={reduce ? false : { opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.55, ease: EASE }}
      >
        <Tile accent={verification.badge ? VERIFY : TRUST} hover={false}>
          <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-5">
              {score !== null ? (
                <Ring value={score} size={78} stroke={9} color={score >= 80 ? VERIFY : score >= 60 ? AMBER : TRUST}>
                  <span className="font-display text-base font-bold text-white">{score}</span>
                </Ring>
              ) : (
                <div className="grid h-[78px] w-[78px] shrink-0 place-items-center rounded-full border border-dashed border-white/20">
                  <span className="font-mono text-[8px] uppercase tracking-wider text-white/45">no score</span>
                </div>
              )}

              <div>
                <h1 className="font-display text-2xl font-bold tracking-tight md:text-3xl">
                  {candidate.name ?? "Candidate"}
                </h1>
                <p className="mt-1 text-[13px] text-white/50">
                  Applied {shortDate(application.applied_at) ?? "—"}
                  {application.mock_attempts > 1 && (
                    <> · {application.mock_attempts} interview attempts, best score shown</>
                  )}
                </p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Pill tone="trust">{STAGE_LABELS[application.stage as ApplicationStage] ?? application.stage}</Pill>
              {/* The stamp is earned, not decorative — and its absence is
                  labelled rather than left blank. */}
              <span
                className="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold"
                style={
                  verification.badge
                    ? { background: "#0da06e1f", borderColor: "#0da06e55", color: "#5fd6a6" }
                    : { background: "rgba(255,255,255,0.04)", borderColor: "rgba(255,255,255,0.10)", color: "rgba(244,247,251,0.55)" }
                }
              >
                <span aria-hidden>{verification.badge ? "✓" : "○"}</span>
                {verification.badge ? "Verified candidate" : "Not fully verified"}
              </span>
            </div>
          </div>

          {/* Contact, or the reason there is none. */}
          <div className="mt-5 border-t border-white/[0.07] pt-4">
            {candidate.contact_visible ? (
              <div className="flex flex-wrap gap-x-8 gap-y-2">
                <span className="font-mono text-[13px] text-white/80">{candidate.email ?? "—"}</span>
                <span className="font-mono text-[13px] text-white/80">{candidate.phone ?? "—"}</span>
              </div>
            ) : (
              <p className="text-[13px] text-white/45">
                Contact details unlock at Shortlisted. Until then you assess the work, not the person.
              </p>
            )}
          </div>
        </Tile>
      </motion.div>

      {/* Verification -------------------------------------------------- */}
      <Tile accent={VERIFY} hover={false}>
        <Label>Verification</Label>
        <h2 className="font-display mt-2 text-xl font-bold tracking-tight">What has actually been checked</h2>
        <p className="mt-2 max-w-2xl text-[13px] leading-relaxed text-white/50">
          Each check reports its result only. BrowseJobs holds the documents behind them; you see
          whether a check passed, not what the candidate submitted.
        </p>

        <ul className="mt-4 grid gap-2.5 sm:grid-cols-2">
          {verification.checks.map((check, i) => {
            const t = tone(check.status);
            return (
              <motion.li
                key={check.kind}
                initial={reduce ? false : { opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: EASE, delay: Math.min(i, 6) * 0.05 }}
                className="rounded-2xl border p-4"
                style={{ background: t.bg, borderColor: t.border }}
              >
                <div className="flex items-baseline justify-between gap-3">
                  <p className="text-[13px] font-semibold text-white">{check.label}</p>
                  {/* Status is named, never colour alone. */}
                  <span className="font-mono text-[10px] uppercase tracking-[0.12em]" style={{ color: t.color }}>
                    {check.status_label}
                  </span>
                </div>
                <p className="mt-1 text-[11px] text-white/40">
                  {checkSubtitle(check.status, check.verified_at)}
                </p>
              </motion.li>
            );
          })}
        </ul>
      </Tile>

      {/* Interview analysis -------------------------------------------- */}
      {interview === null ? (
        <Tile accent={TRUST} hover={false}>
          <Label>Interview</Label>
          <p className="mt-2 text-sm text-white/55">
            This candidate has not sat the interview for this JD yet, so there is nothing to grade.
          </p>
        </Tile>
      ) : (
        <>
          <Tile accent={TRUST} hover={false}>
            <Label>Interview analysis</Label>
            <h2 className="font-display mt-2 text-xl font-bold tracking-tight">
              How they scored, dimension by dimension
            </h2>

            {interview.competencies.length === 0 ? (
              <p className="mt-3 text-sm text-white/50">
                The grader returned an overall score without a per-dimension breakdown.
              </p>
            ) : (
              <ul className="mt-4 space-y-3.5">
                {interview.competencies.map((c) => (
                  <li key={c.name}>
                    <div className="flex items-baseline justify-between gap-3">
                      <span className="text-[13px] font-semibold text-white">{c.name}</span>
                      <span className="font-mono text-[12px] text-white/70">{c.score ?? "—"}</span>
                    </div>
                    <div className="mt-1.5">
                      <SkillMeter
                        pct={c.score ?? 0}
                        color={(c.score ?? 0) >= 80 ? VERIFY : (c.score ?? 0) >= 60 ? AMBER : TRUST}
                      />
                    </div>
                  </li>
                ))}
              </ul>
            )}

            {interview.graded_by && (
              <p className="mt-4 font-mono text-[10px] uppercase tracking-[0.14em] text-white/35">
                Graded by {interview.graded_by === "ai" ? "AI against this JD's rubric" : "the fallback rubric"}
              </p>
            )}
          </Tile>

          <div className="grid gap-4 md:grid-cols-2 md:gap-5">
            <Tile accent={VERIFY} hover={false}>
              <Label>What went well</Label>
              {interview.strengths.length === 0 ? (
                <p className="mt-3 text-[13px] text-white/45">The grader called out no specific strengths.</p>
              ) : (
                <ul className="mt-3 space-y-2.5">
                  {interview.strengths.map((s, i) => (
                    <li key={i} className="flex gap-2.5 text-[13px] leading-relaxed text-white/75">
                      <span aria-hidden style={{ color: "#5fd6a6" }}>▸</span>
                      {s}
                    </li>
                  ))}
                </ul>
              )}
            </Tile>

            <Tile accent={AMBER} hover={false}>
              <Label>Where they were weaker</Label>
              {interview.concerns.length === 0 ? (
                <p className="mt-3 text-[13px] text-white/45">The grader called out no specific concerns.</p>
              ) : (
                <ul className="mt-3 space-y-2.5">
                  {interview.concerns.map((s, i) => (
                    <li key={i} className="flex gap-2.5 text-[13px] leading-relaxed text-white/75">
                      <span aria-hidden style={{ color: "#e8bf63" }}>▸</span>
                      {s}
                    </li>
                  ))}
                </ul>
              )}
            </Tile>
          </div>

          {/* Session facts, including the one that is missing. */}
          <Tile accent={VIOLET} hover={false}>
            <Label>The session itself</Label>
            <dl className="mt-3 grid gap-4 sm:grid-cols-3">
              <div>
                <dt className="text-[11px] uppercase tracking-[0.12em] text-white/35">Mode</dt>
                <dd className="mt-1 font-mono text-[13px] text-white/80">{interview.session.mode ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-[0.12em] text-white/35">Length</dt>
                <dd className="mt-1 font-mono text-[13px] text-white/80">
                  {minutes(interview.session.duration_seconds) ?? "—"}
                </dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-[0.12em] text-white/35">Completed</dt>
                <dd className="mt-1 font-mono text-[13px] text-white/80">
                  {shortDate(interview.session.completed_at) ?? "—"}
                </dd>
              </div>
            </dl>

            {!interview.session.proctoring_captured && (
              <p className="mt-4 rounded-2xl border border-white/[0.10] bg-white/[0.03] px-4 py-3 text-[12px] leading-relaxed text-white/55">
                Proctoring signals were not captured for this session. Read the score as an unsupervised
                assessment — we are not reporting a clean session, we are saying we did not watch one.
              </p>
            )}
          </Tile>
        </>
      )}

      <SendRoundPanel jobId={jobId} applicationId={applicationId} />

      {/* CV ------------------------------------------------------------ */}
      <Tile accent={TRUST} hover={false}>
        <Label>Background</Label>
        {cv === null ? (
          <p className="mt-3 text-[13px] text-white/45">
            This candidate has not built a CV profile on the platform.
          </p>
        ) : (
          <div className="mt-3 space-y-5">
            {cv.summary && <p className="max-w-3xl text-[14px] leading-relaxed text-white/70">{cv.summary}</p>}

            {cv.skills.length > 0 && (
              <div>
                <p className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/35">Skills</p>
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {cv.skills.map((s) => (
                    <span
                      key={s}
                      className="rounded-full border border-white/[0.10] px-2.5 py-1 font-mono text-[10px] text-white/60"
                    >
                      {s}
                    </span>
                  ))}
                </div>
              </div>
            )}

            <CvList title="Experience" rows={cv.experience} />
            <CvList title="Education" rows={cv.education} />
          </div>
        )}
      </Tile>
    </div>
  );
}

/**
 * Sending a designed round to this candidate by hand (PRD-E F18).
 *
 * Rounds already sat are listed as sent rather than hidden, so it is obvious
 * why a button is missing. Rounds the employer marked automatic still show a
 * send button: a threshold is a floor, not a prohibition on judgement.
 */
function SendRoundPanel({ jobId, applicationId }: { jobId: number; applicationId: number }) {
  const { workspace } = useWorkspace();
  const [rounds, setRounds] = useState<JobRound[] | null>(null);
  const [sent, setSent] = useState<Record<string, boolean>>({});
  const [busy, setBusy] = useState<number | null>(null);
  const [note, setNote] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      employerApi.rounds(workspace.id, jobId),
      employerApi.interviews(workspace.id, jobId, applicationId),
    ])
      .then(([process, interviews]) => {
        setRounds(process.data.filter((r) => r.enabled && r.kind !== "human"));
        setSent(Object.fromEntries(interviews.data.map((i) => [i.round, true])));
      })
      .catch(() => setRounds([]));
  }, [workspace.id, jobId, applicationId]);

  async function send(round: JobRound) {
    setBusy(round.id);
    setError(null);
    setNote(null);
    try {
      await employerApi.sendRound(workspace.id, jobId, applicationId, round.id);
      setSent((prev) => ({ ...prev, [round.key]: true }));
      setNote(`${round.name} sent. The candidate has ${round.window_hours} hours.`);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not send the round.");
    } finally {
      setBusy(null);
    }
  }

  if (rounds === null) return <Skeleton className="h-32" />;

  if (rounds.length === 0) {
    return (
      <Tile accent={TRUST} hover={false}>
        <Label>Interview rounds</Label>
        <p className="mt-2 text-[13px] text-white/50">
          This role runs no platform rounds. Design them on the JD&apos;s Process tab.
        </p>
      </Tile>
    );
  }

  return (
    <Tile accent={TRUST} hover={false}>
      <Label>Interview rounds</Label>
      <h2 className="font-display mt-2 text-xl font-bold tracking-tight">Send a round</h2>
      <p className="mt-2 max-w-2xl text-[13px] leading-relaxed text-white/50">
        Each round asks what this candidate has not been asked yet. Sending one does not move their
        stage — that stays a decision you make.
      </p>

      <ul className="mt-4 space-y-2.5">
        {rounds.map((round) => (
          <li
            key={round.id}
            className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/[0.08] bg-white/[0.02] p-4"
          >
            <div>
              <p className="text-[13px] font-semibold text-white">{round.name}</p>
              <p className="mt-0.5 font-mono text-[11px] text-white/40">
                {round.question_count ?? "all matching"} questions · {round.window_hours}h
                {round.dispatch === "auto" && round.auto_min_score !== null && (
                  <> · automatic at {round.auto_min_score}%</>
                )}
              </p>
            </div>
            {sent[round.key] ? (
              <Pill tone="verify">Sent</Pill>
            ) : (
              <button
                type="button"
                onClick={() => void send(round)}
                disabled={busy === round.id}
                className="rounded-full border border-white/[0.14] px-4 py-1.5 text-xs font-medium text-white/80 transition-colors hover:border-white/30 disabled:opacity-40"
              >
                {busy === round.id ? "Sending…" : "Send"}
              </button>
            )}
          </li>
        ))}
      </ul>

      {note && <p className="mt-3 text-[13px]" style={{ color: "#6ee7b7" }}>{note}</p>}
      {error && <p className="mt-3 text-[13px]" style={{ color: "#fca5a5" }}>{error}</p>}
    </Tile>
  );
}

/**
 * CV sections arrive as free-form parsed objects — a resume parser cannot
 * promise a fixed shape. Rather than guessing at keys and dropping whatever
 * does not fit, the common ones are pulled to the front and the rest are
 * printed as labelled fields.
 */
function CvList({ title, rows }: { title: string; rows: Record<string, unknown>[] }) {
  if (rows.length === 0) return null;

  return (
    <div>
      <p className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/35">{title}</p>
      <ul className="mt-2 space-y-2.5">
        {rows.map((row, i) => {
          const head = [row.title, row.role, row.degree, row.qualification].find(
            (v): v is string => typeof v === "string" && v !== "",
          );
          const where = [row.company, row.organisation, row.institution, row.school].find(
            (v): v is string => typeof v === "string" && v !== "",
          );
          const when = [row.dates, row.period, row.year, row.duration].find(
            (v): v is string => typeof v === "string" && v !== "",
          );
          const rest = Object.entries(row).filter(
            ([k, v]) =>
              typeof v === "string" &&
              v !== "" &&
              ![
                "title", "role", "degree", "qualification",
                "company", "organisation", "institution", "school",
                "dates", "period", "year", "duration",
              ].includes(k),
          );

          return (
            <li key={i} className="rounded-2xl border border-white/[0.08] bg-white/[0.02] p-4">
              <p className="text-[13px] font-semibold text-white">{head ?? `${title} entry ${i + 1}`}</p>
              {(where || when) && (
                <p className="mt-0.5 text-[12px] text-white/50">
                  {[where, when].filter(Boolean).join(" · ")}
                </p>
              )}
              {rest.map(([k, v]) => (
                <p key={k} className="mt-1.5 text-[12px] leading-relaxed text-white/55">
                  <span className="font-mono text-[10px] uppercase tracking-[0.1em] text-white/30">{k}</span>{" "}
                  {v as string}
                </p>
              ))}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
