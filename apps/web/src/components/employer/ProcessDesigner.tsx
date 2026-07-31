"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { GhostButton, Label, Pill, PrimaryButton, Skeleton, Tile } from "@/components/employer/ui";
import { AMBER, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
import { employerApi, type InterviewProcessData, type JobRound } from "@/lib/employer";

/**
 * The interview process designer (PRD-E F18).
 *
 * A JD's process is an ordered list of rounds. Each one says what it tests,
 * how long the candidate has, and whether it goes out by hand or on a score.
 *
 * Two rules are enforced in the UI as well as the API, because both are
 * easier to understand as a disabled control than as a rejected save:
 *
 *  - A round that has already been sat can be switched off but not removed.
 *    Deleting it would orphan the record of what a candidate was asked.
 *  - A human round cannot be automatic. It is a call someone schedules; the
 *    platform has nothing to send.
 */

type Draft = Omit<JobRound, "id" | "position" | "has_interviews"> & {
  id?: number;
  has_interviews?: boolean;
};

const KIND_LABEL: Record<string, string> = {
  ai_interview: "AI interview",
  mcq: "Multiple choice",
  human: "Human round (off-platform)",
};

const KIND_HINT: Record<string, string> = {
  ai_interview: "Spoken or typed answers, graded against this round's rubric.",
  mcq: "Objective questions, scored automatically.",
  human: "A call or on-site you run. Tracked here, not generated or graded.",
};

const FIELD =
  "w-full rounded-xl border border-white/[0.12] bg-white/[0.05] px-3 py-2 text-sm text-white outline-none placeholder:text-white/30 focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25";

function blankRound(windowHours: number): Draft {
  return {
    key: "",
    name: "",
    kind: "ai_interview",
    focus_skills: [],
    competency_weights: {},
    format_mix: {},
    question_count: null,
    notes: null,
    window_hours: windowHours,
    dispatch: "manual",
    auto_min_score: null,
    enabled: true,
  };
}

export function ProcessDesigner({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [meta, setMeta] = useState<InterviewProcessData["meta"] | null>(null);
  const [rounds, setRounds] = useState<Draft[] | null>(null);
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    employerApi
      .rounds(workspace.id, jobId)
      .then((r) => {
        setMeta(r.meta);
        setRounds(r.data);
      })
      .catch(() => setError("Could not load the process."));
  }, [workspace.id, jobId]);

  useEffect(load, [load]);

  function patch(index: number, next: Partial<Draft>) {
    setRounds((prev) =>
      (prev ?? []).map((r, i) => {
        if (i !== index) return r;
        const merged = { ...r, ...next };
        // Keeping this in one place means the toggle and the kind picker
        // cannot disagree about whether a human round can be automatic.
        if (merged.kind === "human") merged.dispatch = "manual";
        if (merged.dispatch === "manual") merged.auto_min_score = null;
        return merged;
      }),
    );
    setSaved(null);
  }

  function move(index: number, by: number) {
    setRounds((prev) => {
      if (!prev) return prev;
      const target = index + by;
      if (target < 0 || target >= prev.length) return prev;
      const next = [...prev];
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
    setSaved(null);
  }

  function remove(index: number) {
    setRounds((prev) => (prev ?? []).filter((_, i) => i !== index));
    setSaved(null);
  }

  async function save() {
    if (!rounds) return;
    setBusy(true);
    setError(null);
    setSaved(null);
    try {
      const res = await employerApi.saveRounds(
        workspace.id,
        jobId,
        rounds.map((r) => ({
          key: r.key || undefined,
          name: r.name,
          kind: r.kind,
          focus_skills: r.focus_skills,
          competency_weights: r.competency_weights,
          format_mix: r.format_mix,
          question_count: r.question_count,
          notes: r.notes,
          window_hours: r.window_hours,
          dispatch: r.dispatch,
          auto_min_score: r.auto_min_score,
          enabled: r.enabled,
        })),
      );
      setRounds(res.data);
      setSaved("Saved. Interviews already sent keep the questions they were sent with.");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save.");
    } finally {
      setBusy(false);
    }
  }

  if (error && rounds === null) {
    return (
      <Tile accent={TRUST} hover={false}>
        <p className="text-sm text-white/60">{error}</p>
      </Tile>
    );
  }

  if (!rounds || !meta) {
    return <div className="space-y-4">{[0, 1].map((i) => <Skeleton key={i} className="h-48" />)}</div>;
  }

  return (
    <div className="space-y-5">
      <Tile accent={VIOLET} hover={false}>
        <Label>Interview process</Label>
        <h2 className="font-display mt-2 text-xl font-bold leading-tight md:text-2xl">
          The rounds this role runs, in order.
        </h2>
        <p className="mt-2.5 max-w-2xl text-sm leading-relaxed text-white/55">
          Every round draws from this JD&apos;s question bank, skipping anything the candidate has
          already been asked. Set a focus and the round leans on it.
        </p>
        {!meta.has_mock && (
          <p className="mt-3 text-[13px] text-[#e8bf63]">
            This JD has no question bank yet — publish it, or generate the mock, before sending a round.
          </p>
        )}
      </Tile>

      {rounds.length === 0 && (
        <Tile accent={TRUST} className="py-10 text-center" hover={false}>
          <p className="font-display text-lg font-bold tracking-tight">No rounds</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-white/55">
            Candidates apply and are graded, but nothing follows. Add a round below.
          </p>
        </Tile>
      )}

      {rounds.map((round, i) => (
        <RoundCard
          key={round.id ?? `new-${i}`}
          round={round}
          index={i}
          total={rounds.length}
          skills={meta.selectable_skills}
          onPatch={(next) => patch(i, next)}
          onMove={(by) => move(i, by)}
          onRemove={() => remove(i)}
        />
      ))}

      <div className="flex flex-wrap items-center gap-3">
        <GhostButton
          onClick={() => {
            setRounds([...(rounds ?? []), blankRound(meta.default_window_hours)]);
            setSaved(null);
          }}
        >
          + Add a round
        </GhostButton>
        <PrimaryButton onClick={save} disabled={busy || rounds.some((r) => r.name.trim() === "")}>
          {busy ? "Saving…" : "Save process"}
        </PrimaryButton>
        {rounds.some((r) => r.name.trim() === "") && (
          <span className="text-[12px] text-white/45">Every round needs a name.</span>
        )}
      </div>

      {saved && <p className="text-[13px]" style={{ color: "#6ee7b7" }}>{saved}</p>}
      {error && <p className="text-[13px]" style={{ color: "#fca5a5" }}>{error}</p>}
    </div>
  );
}

function RoundCard({
  round, index, total, skills, onPatch, onMove, onRemove,
}: {
  round: Draft;
  index: number;
  total: number;
  skills: string[];
  onPatch: (next: Partial<Draft>) => void;
  onMove: (by: number) => void;
  onRemove: () => void;
}) {
  const accent = round.enabled ? (round.dispatch === "auto" ? VERIFY : TRUST) : AMBER;

  return (
    <Tile accent={accent} hover={false}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="font-mono text-[11px] text-white/35">
            {String(index + 1).padStart(2, "0")}
          </span>
          <input
            value={round.name}
            onChange={(e) => onPatch({ name: e.target.value })}
            placeholder="Round name"
            aria-label={`Round ${index + 1} name`}
            className={`${FIELD} !w-64 font-semibold`}
          />
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {!round.enabled && <Pill tone="amber">Switched off</Pill>}
          {round.has_interviews && <Pill tone="neutral">Already sat</Pill>}
          <button
            type="button"
            onClick={() => onMove(-1)}
            disabled={index === 0}
            aria-label="Move earlier"
            className="rounded-lg border border-white/[0.14] px-2.5 py-1 text-xs text-white/70 disabled:opacity-30"
          >
            ↑
          </button>
          <button
            type="button"
            onClick={() => onMove(1)}
            disabled={index === total - 1}
            aria-label="Move later"
            className="rounded-lg border border-white/[0.14] px-2.5 py-1 text-xs text-white/70 disabled:opacity-30"
          >
            ↓
          </button>
          <button
            type="button"
            onClick={() => onPatch({ enabled: !round.enabled })}
            className="rounded-lg border border-white/[0.14] px-2.5 py-1 text-xs text-white/70 hover:border-white/30"
          >
            {round.enabled ? "Switch off" : "Switch on"}
          </button>
          {/* Removal is only offered when nothing has been sat: the API
              disables such a round instead, and a button that silently does
              something else is worse than no button. */}
          {!round.has_interviews && (
            <button
              type="button"
              onClick={onRemove}
              className="rounded-lg border border-[#e0556155] px-2.5 py-1 text-xs text-[#f2a1a7] hover:border-[#e05561]"
            >
              Remove
            </button>
          )}
        </div>
      </div>

      <div className="mt-4 grid gap-4 md:grid-cols-3">
        <div>
          <Label>Kind</Label>
          <select
            value={round.kind}
            onChange={(e) => onPatch({ kind: e.target.value as Draft["kind"] })}
            aria-label={`Round ${index + 1} kind`}
            className={`${FIELD} mt-2`}
          >
            {Object.entries(KIND_LABEL).map(([k, label]) => (
              <option key={k} value={k} className="text-[#0a1220]">{label}</option>
            ))}
          </select>
          <p className="mt-1.5 text-[11px] leading-snug text-white/40">{KIND_HINT[round.kind]}</p>
        </div>

        <div>
          <Label>Questions</Label>
          <input
            type="number"
            min={4}
            max={20}
            value={round.question_count ?? ""}
            onChange={(e) => onPatch({ question_count: e.target.value === "" ? null : Number(e.target.value) })}
            placeholder="All that match"
            aria-label={`Round ${index + 1} question count`}
            disabled={round.kind === "human"}
            className={`${FIELD} mt-2 font-mono disabled:opacity-40`}
          />
          <p className="mt-1.5 text-[11px] text-white/40">Drawn from this JD&apos;s bank.</p>
        </div>

        <div>
          <Label>Time to complete</Label>
          <input
            type="number"
            min={1}
            max={720}
            value={round.window_hours}
            onChange={(e) => onPatch({ window_hours: Number(e.target.value) })}
            aria-label={`Round ${index + 1} window in hours`}
            disabled={round.kind === "human"}
            className={`${FIELD} mt-2 font-mono disabled:opacity-40`}
          />
          <p className="mt-1.5 text-[11px] text-white/40">Hours from the invite.</p>
        </div>
      </div>

      {/* Focus ---------------------------------------------------------- */}
      {round.kind !== "human" && (
        <div className="mt-4">
          <Label>What this round leans on</Label>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {skills.slice(0, 24).map((skill) => {
              const on = round.focus_skills.includes(skill);
              return (
                <button
                  key={skill}
                  type="button"
                  aria-pressed={on}
                  onClick={() =>
                    onPatch({
                      focus_skills: on
                        ? round.focus_skills.filter((s) => s !== skill)
                        : [...round.focus_skills, skill],
                    })
                  }
                  className="rounded-full border px-3 py-1.5 font-mono text-[10px] uppercase tracking-[0.08em] transition-colors"
                  style={{
                    borderColor: on ? `${TRUST}66` : "rgba(255,255,255,0.14)",
                    background: on ? `${TRUST}1f` : "transparent",
                    color: on ? "#9dc2fb" : "rgba(255,255,255,0.55)",
                  }}
                >
                  {on ? "✓ " : "+ "}
                  {skill}
                </button>
              );
            })}
          </div>
          {round.focus_skills.length === 0 && (
            <p className="mt-2 text-[12px] text-white/35">
              Nothing selected — this round takes whatever the candidate has not been asked yet.
            </p>
          )}
        </div>
      )}

      {/* Dispatch ------------------------------------------------------- */}
      <div className="mt-5 border-t border-white/[0.07] pt-4">
        <Label>How it goes out</Label>
        {round.kind === "human" ? (
          <p className="mt-2 text-[13px] text-white/50">
            You schedule this one. Nothing is sent from here.
          </p>
        ) : (
          <>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              {(["manual", "auto"] as const).map((mode) => (
                <button
                  key={mode}
                  type="button"
                  aria-pressed={round.dispatch === mode}
                  onClick={() => onPatch({ dispatch: mode, auto_min_score: mode === "auto" ? (round.auto_min_score ?? 75) : null })}
                  className="rounded-full border px-4 py-1.5 text-xs font-medium transition-colors"
                  style={{
                    borderColor: round.dispatch === mode ? `${VERIFY}66` : "rgba(255,255,255,0.14)",
                    background: round.dispatch === mode ? `${VERIFY}1f` : "transparent",
                    color: round.dispatch === mode ? "#5fd6a6" : "rgba(255,255,255,0.6)",
                  }}
                >
                  {mode === "manual" ? "I send it" : "Send it automatically"}
                </button>
              ))}
            </div>

            {round.dispatch === "auto" && (
              <div className="mt-3 flex flex-wrap items-center gap-3">
                <label className="text-[13px] text-white/70" htmlFor={`auto-${index}`}>
                  when the previous score is at least
                </label>
                <input
                  id={`auto-${index}`}
                  type="number"
                  min={1}
                  max={100}
                  value={round.auto_min_score ?? ""}
                  onChange={(e) => onPatch({ auto_min_score: e.target.value === "" ? null : Number(e.target.value) })}
                  className={`${FIELD} !w-24 font-mono`}
                />
                <span className="text-[13px] text-white/70">%</span>
              </div>
            )}

            <p className="mt-2.5 text-[12px] leading-relaxed text-white/40">
              {round.dispatch === "auto"
                ? "Sending a round does not move the candidate's stage — that stays a decision someone makes, and it is recorded as one."
                : "Send it from a candidate's profile when you are ready."}
            </p>
          </>
        )}
      </div>
    </Tile>
  );
}
