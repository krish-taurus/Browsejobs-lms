"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { GhostButton, Label, Pill, PrimaryButton, Skeleton, Tile } from "@/components/employer/ui";
import { AMBER, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
import { employerApi, type InterviewProcessData, type JobRound, type MemberRow } from "@/lib/employer";

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
  "w-full rounded-xl border border-[var(--bj-dash-border)] bg-white px-3 py-2 text-sm text-[var(--bj-dash-ink)] outline-none placeholder:text-[var(--bj-dash-muted)] focus:border-[var(--bj-dash-primary)] focus:ring-4 focus:ring-[var(--bj-dash-primary)]/15";

function blankRound(windowHours: number): Draft {
  return {
    key: "",
    name: "",
    kind: "ai_interview",
    assigned_member_id: null,
    assigned_member_name: null,
    focus_skills: [],
    competency_weights: {},
    format_mix: {},
    selected_questions: [],
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
  const [members, setMembers] = useState<MemberRow[]>([]);
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
  useEffect(() => {
    // Only needed for human rounds' assignee picker — a quiet best-effort
    // fetch, not worth its own error state if it fails.
    employerApi.members(workspace.id).then((r) => setMembers(r.data)).catch(() => setMembers([]));
  }, [workspace.id]);

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
          assigned_member_id: r.assigned_member_id,
          focus_skills: r.focus_skills,
          competency_weights: r.competency_weights,
          format_mix: r.format_mix,
          selected_questions: r.selected_questions,
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
        <p className="text-sm text-[var(--bj-dash-muted)]">{error}</p>
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
        <h2 className="bj-dash-serif mt-2 text-xl leading-tight md:text-2xl">
          The rounds this role runs, in order.
        </h2>
        <p className="mt-2.5 max-w-2xl text-sm leading-relaxed text-[var(--bj-dash-muted)]">
          Every round draws from this JD&apos;s question bank, skipping anything the candidate has
          already been asked. Set a focus and the round leans on it.
        </p>
        {!meta.has_mock && (
          <p className="mt-3 text-[13px] text-[var(--bj-dash-score-fair)]">
            This JD has no question bank yet — publish it, or generate the mock, before sending a round.
          </p>
        )}
      </Tile>

      {rounds.length === 0 && (
        <Tile accent={TRUST} className="py-10 text-center" hover={false}>
          <p className="bj-dash-serif text-lg tracking-tight">No rounds</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-[var(--bj-dash-muted)]">
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
          bank={meta.mock_questions}
          members={members}
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
          <span className="text-[12px] text-[var(--bj-dash-muted)]">Every round needs a name.</span>
        )}
      </div>

      {saved && <p className="text-[13px]" style={{ color: "var(--bj-dash-score-strong)" }}>{saved}</p>}
      {error && <p className="text-[13px]" style={{ color: "var(--bj-dash-score-below)" }}>{error}</p>}
    </div>
  );
}

function RoundCard({
  round, index, total, skills, bank, members, onPatch, onMove, onRemove,
}: {
  round: Draft;
  index: number;
  total: number;
  skills: string[];
  bank: { text: string; skill: string | null; type: string | null }[];
  members: MemberRow[];
  onPatch: (next: Partial<Draft>) => void;
  onMove: (by: number) => void;
  onRemove: () => void;
}) {
  const accent = round.enabled ? (round.dispatch === "auto" ? VERIFY : TRUST) : AMBER;
  // Local display toggle, not persisted: whether the picker is showing at
  // all is a UI convenience, while what's checked lives in
  // round.selected_questions (empty either way behaves as "auto" on save).
  const [pickMode, setPickMode] = useState<"auto" | "manual">(
    round.selected_questions.length > 0 ? "manual" : "auto",
  );

  return (
    <Tile accent={accent} hover={false}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="font-mono text-[11px] text-[var(--bj-dash-muted)]">
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
            className="rounded-lg border border-[var(--bj-dash-border)] px-2.5 py-1 text-xs text-[var(--bj-dash-muted)] disabled:opacity-30"
          >
            ↑
          </button>
          <button
            type="button"
            onClick={() => onMove(1)}
            disabled={index === total - 1}
            aria-label="Move later"
            className="rounded-lg border border-[var(--bj-dash-border)] px-2.5 py-1 text-xs text-[var(--bj-dash-muted)] disabled:opacity-30"
          >
            ↓
          </button>
          <button
            type="button"
            onClick={() => onPatch({ enabled: !round.enabled })}
            className="rounded-lg border border-[var(--bj-dash-border)] px-2.5 py-1 text-xs text-[var(--bj-dash-muted)] hover:border-[var(--bj-dash-primary)]/40 hover:text-[var(--bj-dash-primary)]"
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
              className="rounded-lg border border-[var(--bj-dash-score-below-bg)] px-2.5 py-1 text-xs text-[var(--bj-dash-score-below)] hover:border-[var(--bj-dash-score-below)]"
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
              <option key={k} value={k} className="text-[var(--bj-dash-ink)]">{label}</option>
            ))}
          </select>
          <p className="mt-1.5 text-[11px] leading-snug text-[var(--bj-dash-muted)]">{KIND_HINT[round.kind]}</p>
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
            disabled={round.kind === "human" || round.selected_questions.length > 0}
            className={`${FIELD} mt-2 font-mono disabled:opacity-40`}
          />
          <p className="mt-1.5 text-[11px] text-[var(--bj-dash-muted)]">
            {round.selected_questions.length > 0
              ? "Not used — the exact questions picked below decide the count."
              : "Drawn from this JD's bank."}
          </p>
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
          <p className="mt-1.5 text-[11px] text-[var(--bj-dash-muted)]">Hours from the invite.</p>
        </div>
      </div>

      {/* Focus / questions ------------------------------------------------ */}
      {round.kind !== "human" && (
        <div className="mt-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <Label>What this round asks</Label>
            <div className="flex items-center gap-1.5">
              {(["auto", "manual"] as const).map((mode) => (
                <button
                  key={mode}
                  type="button"
                  aria-pressed={pickMode === mode}
                  onClick={() => {
                    setPickMode(mode);
                    if (mode === "auto") onPatch({ selected_questions: [] });
                  }}
                  className="rounded-full border px-3 py-1 text-[11px] font-medium transition-colors"
                  style={{
                    borderColor: pickMode === mode ? "var(--bj-dash-primary)66" : "var(--bj-dash-border)",
                    background: pickMode === mode ? "var(--bj-dash-soft)" : "transparent",
                    color: pickMode === mode ? "var(--bj-dash-primary)" : "var(--bj-dash-muted)",
                  }}
                >
                  {mode === "auto" ? "Auto by topic" : "Choose exact questions"}
                </button>
              ))}
            </div>
          </div>

          {pickMode === "auto" ? (
            <>
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
                        borderColor: on ? `${TRUST}66` : "var(--bj-dash-border)",
                        background: on ? `${TRUST}1f` : "transparent",
                        color: on ? "var(--bj-dash-primary)" : "var(--bj-dash-muted)",
                      }}
                    >
                      {on ? "✓ " : "+ "}
                      {skill}
                    </button>
                  );
                })}
              </div>
              {round.focus_skills.length === 0 && (
                <p className="mt-2 text-[12px] text-[var(--bj-dash-muted)]">
                  Nothing selected — this round takes whatever the candidate has not been asked yet.
                </p>
              )}
            </>
          ) : bank.length === 0 ? (
            <p className="mt-2 text-[12px] text-[var(--bj-dash-muted)]">
              No question bank yet for this JD — publish it, or generate the mock, to pick from it.
            </p>
          ) : (
            <>
              <div className="mt-2 max-h-72 space-y-1.5 overflow-y-auto rounded-xl border p-2.5" style={{ borderColor: "var(--bj-dash-border)" }}>
                {bank.map((q) => {
                  const on = round.selected_questions.includes(q.text);
                  return (
                    <label
                      key={q.text}
                      className="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-[13px] leading-snug transition-colors"
                      style={{ background: on ? "var(--bj-dash-soft)" : "transparent" }}
                    >
                      <input
                        type="checkbox"
                        checked={on}
                        onChange={() =>
                          onPatch({
                            selected_questions: on
                              ? round.selected_questions.filter((t) => t !== q.text)
                              : [...round.selected_questions, q.text],
                          })
                        }
                        className="mt-0.5 size-3.5 accent-[var(--bj-dash-primary)]"
                      />
                      <span style={{ color: "var(--bj-dash-ink)" }}>
                        {q.text}
                        {q.skill && (
                          <span className="ml-1.5 font-mono text-[10px] uppercase tracking-wide" style={{ color: "var(--bj-dash-muted)" }}>
                            {q.skill}
                          </span>
                        )}
                      </span>
                    </label>
                  );
                })}
              </div>
              <p className="mt-2 text-[12px] text-[var(--bj-dash-muted)]">
                {round.selected_questions.length === 0
                  ? "Nothing picked yet — pick at least one, or switch back to Auto by topic."
                  : `${round.selected_questions.length} question${round.selected_questions.length === 1 ? "" : "s"} picked. Sent in this order, regardless of "Questions" below.`}
              </p>
            </>
          )}
        </div>
      )}

      {/* Who takes it ----------------------------------------------------- */}
      {round.kind === "human" && (
        <div className="mt-4">
          <Label>Who takes this round</Label>
          {members.length === 0 ? (
            <p className="mt-2 text-[12px] text-[var(--bj-dash-muted)]">
              No one is on this workspace&apos;s team yet — add a teammate on the Team page before assigning this round.
            </p>
          ) : (
            <>
              <select
                value={round.assigned_member_id ?? ""}
                onChange={(e) => {
                  const id = e.target.value === "" ? null : Number(e.target.value);
                  const picked = members.find((m) => m.id === id);
                  onPatch({ assigned_member_id: id, assigned_member_name: picked?.user?.name ?? null });
                }}
                aria-label={`Round ${index + 1} assignee`}
                className={`${FIELD} mt-2 max-w-xs`}
              >
                <option value="" className="text-[var(--bj-dash-ink)]">Not assigned yet</option>
                {members.map((m) => (
                  <option key={m.id} value={m.id} className="text-[var(--bj-dash-ink)]">
                    {m.user?.name ?? m.user?.email ?? `Member #${m.id}`}
                  </option>
                ))}
              </select>
              <p className="mt-1.5 text-[11px] text-[var(--bj-dash-muted)]">
                Tracked here so it&apos;s on record who runs it — the invite and the call itself still happen off-platform.
              </p>
            </>
          )}
        </div>
      )}

      {/* Dispatch ------------------------------------------------------- */}
      <div className="mt-5 border-t border-[var(--bj-dash-border)] pt-4">
        <Label>How it goes out</Label>
        {round.kind === "human" ? (
          <p className="mt-2 text-[13px] text-[var(--bj-dash-muted)]">
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
                    borderColor: round.dispatch === mode ? `${VERIFY}66` : "var(--bj-dash-border)",
                    background: round.dispatch === mode ? `${VERIFY}1f` : "transparent",
                    color: round.dispatch === mode ? "var(--bj-dash-score-strong)" : "var(--bj-dash-muted)",
                  }}
                >
                  {mode === "manual" ? "I send it" : "Send it automatically"}
                </button>
              ))}
            </div>

            {round.dispatch === "auto" && (
              <div className="mt-3 flex flex-wrap items-center gap-3">
                <label className="text-[13px] text-[var(--bj-dash-muted)]" htmlFor={`auto-${index}`}>
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
                <span className="text-[13px] text-[var(--bj-dash-muted)]">%</span>
              </div>
            )}

            <p className="mt-2.5 text-[12px] leading-relaxed text-[var(--bj-dash-muted)]">
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
