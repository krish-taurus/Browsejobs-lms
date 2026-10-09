"use client";

import { useState, type ReactNode } from "react";
import { JobIcon } from "@/components/jobs/JobIcons";
import { ReadinessIllustration } from "@/components/jobs/ReadinessIllustration";
import { mockRecordingApi, type MyMockStatus } from "@/lib/candidate";

export const FOCUS_RING = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-trust focus-visible:ring-offset-2";

/** Small status chip — amber while in progress, green once done, muted otherwise. */
export function StatusChip({ tone, children }: { tone: "progress" | "done" | "muted"; children: ReactNode }) {
  const styles = {
    progress: "bg-amber/15 text-ink",
    done: "bg-verify-bg text-verify",
    muted: "bg-paper text-muted",
  }[tone];
  const dot = { progress: "bg-amber", done: "bg-verify", muted: "bg-muted" }[tone];

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${styles}`}>
      <span className={`h-2 w-2 rounded-full ${dot}`} aria-hidden="true" />
      {children}
    </span>
  );
}

/**
 * The AI Readiness Interview — CV-driven, not tied to any job. Completing it
 * is what makes a student's profile visible to employers browsing BrowseJobs
 * talent; it does not unlock any job's Apply (each job has its own interview).
 */
export function ReadinessBanner({ status, busy, onStart }: { status: MyMockStatus | null; busy: boolean; onStart: () => void }) {
  const [recordingBusy, setRecordingBusy] = useState(false);
  const [recordingDeleted, setRecordingDeleted] = useState(false);
  const [recordingError, setRecordingError] = useState<string | null>(null);

  if (status === null) {
    return <div className="shimmer h-[220px] rounded-[18px]" aria-busy="true" />;
  }

  const usedUp = status.attempts.used >= status.attempts.limit;
  const done = status.status === "completed";
  const inProgress = status.status === "in_progress";
  const showRecordingActions = done && status.has_recording && !recordingDeleted && status.mock_id !== null;

  async function viewRecording() {
    if (status === null || status.mock_id === null) return;
    setRecordingBusy(true);
    setRecordingError(null);
    try {
      const r = await mockRecordingApi.view(status.mock_id);
      window.open(r.data.url, "_blank", "noopener,noreferrer");
    } catch {
      setRecordingError("Couldn't open the recording — try again.");
    } finally {
      setRecordingBusy(false);
    }
  }

  async function deleteRecording() {
    if (status === null || status.mock_id === null) return;
    if (!window.confirm("Delete this recording? Your score stays the same — only the video goes.")) return;
    setRecordingBusy(true);
    setRecordingError(null);
    try {
      await mockRecordingApi.remove(status.mock_id);
      setRecordingDeleted(true);
    } catch {
      setRecordingError("Couldn't delete the recording — try again.");
    } finally {
      setRecordingBusy(false);
    }
  }

  const description = done
    ? "Your profile is visible to employers browsing BrowseJobs talent — even for jobs you haven't applied to."
    : inProgress
      ? "Continue your AI readiness interview and showcase your skills beyond your CV."
      : usedUp
        ? "You've used every attempt for this interview."
        : "15 questions built from your own CV. Complete it once and employers browsing BrowseJobs talent can find you.";

  const cta = inProgress
    ? "Resume readiness interview"
    : done
      ? usedUp ? null : "Retake for a higher score"
      : usedUp ? null : "Start readiness interview";

  return (
    <section
      aria-labelledby="readiness-title"
      className="relative overflow-hidden rounded-[18px] border border-trust/20 bg-gradient-to-br from-sky via-white to-sky/70 p-6 shadow-soft"
    >
      <div className="grid items-center gap-4 md:grid-cols-[minmax(0,1fr)_190px]">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-full border border-trust/30 bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-trust">
              AI Readiness
            </span>
            {inProgress && <StatusChip tone="progress">In progress</StatusChip>}
            {done && <StatusChip tone="done">Completed · score <span className="mono">{status.score}</span></StatusChip>}
            {!done && !inProgress && usedUp && <StatusChip tone="muted">No attempts left</StatusChip>}
          </div>

          <h2 id="readiness-title" className="display mt-3 text-[24px] leading-tight text-ink sm:text-[26px]">
            Get noticed, before you even apply.
          </h2>
          <p className="mt-2 max-w-xl text-[15px] text-muted">{description}</p>

          <p className="mt-3 flex items-start gap-1.5 text-sm text-muted">
            <JobIcon name="info" className="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              Not tied to a specific job <span aria-hidden="true">·</span>{" "}
              <span className="mono whitespace-nowrap">{status.attempts.used} of {status.attempts.limit}</span> attempts used
            </span>
          </p>

          {(cta || showRecordingActions) && (
            <div className="mt-5 flex flex-wrap items-center gap-3">
              {cta && (
                <button
                  type="button"
                  onClick={onStart}
                  disabled={busy}
                  aria-busy={busy}
                  className={`inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-full px-6 text-[15px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto ${
                    done ? "border border-trust bg-white text-trust hover:bg-sky" : "bg-trust text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] hover:bg-deep"
                  } ${FOCUS_RING}`}
                >
                  {busy ? "Starting…" : cta}
                  {!busy && <JobIcon name="arrowRight" className="h-[18px] w-[18px]" />}
                </button>
              )}
              {showRecordingActions && (
                <>
                  <button
                    type="button"
                    onClick={viewRecording}
                    disabled={recordingBusy}
                    className={`inline-flex min-h-[44px] items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-trust disabled:opacity-50 ${FOCUS_RING}`}
                  >
                    <JobIcon name="play" className="h-4 w-4" /> View recording
                  </button>
                  <button
                    type="button"
                    onClick={deleteRecording}
                    disabled={recordingBusy}
                    className={`min-h-[44px] rounded-full px-3 text-sm text-muted hover:text-warn disabled:opacity-50 ${FOCUS_RING}`}
                  >
                    Delete recording
                  </button>
                </>
              )}
            </div>
          )}
          {recordingError && <p role="alert" className="mt-2 text-xs text-warn">{recordingError}</p>}
        </div>

        <div className="hidden justify-center md:flex">
          <ReadinessIllustration className="h-auto w-full max-w-[190px]" />
        </div>
      </div>
    </section>
  );
}
