"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { GhostButton, Label, Pill, PrimaryButton, Skeleton, Tile } from "@/components/employer/ui";
import { AMBER, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
import { employerApi, type MockDesignData } from "@/lib/employer";

/**
 * The interview designer (PRD-E F16).
 *
 * Weights are entered as plain numbers and the share each one represents is
 * shown live beside it. The employer never has to make them total 100 —
 * that arithmetic is the thing that makes people abandon a settings screen,
 * and the API normalises a ratio anyway.
 *
 * Saving deliberately does not regenerate: candidates may be part-way
 * through the current questions. When a mock already exists the panel says
 * so and offers the regenerate as a separate, deliberate action.
 */

const MAX_WEIGHT = 10;

export function MockDesigner({ jobId }: { jobId: number }) {
  const { workspace } = useWorkspace();
  const [data, setData] = useState<MockDesignData | null>(null);
  const [focus, setFocus] = useState<string[]>([]);
  const [competencies, setCompetencies] = useState<Record<string, number>>({});
  const [formats, setFormats] = useState<Record<string, number>>({});
  const [count, setCount] = useState<number | "">("");
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    employerApi
      .mockDesign(workspace.id, jobId)
      .then((r) => {
        setData(r.data);
        setFocus(r.data.design.focus_skills ?? []);
        setCompetencies(r.data.design.competency_weights ?? {});
        setFormats(r.data.design.format_mix ?? {});
        setCount(r.data.design.question_count ?? "");
        setNotes(r.data.design.notes ?? "");
      })
      .catch(() => setError("Could not load the designer."));
  }, [workspace.id, jobId]);

  useEffect(load, [load]);

  // Shown live so the ratio is legible without the employer doing the maths.
  const share = useCallback((weights: Record<string, number>, key: string): number => {
    const total = Object.values(weights).reduce((a, b) => a + (b || 0), 0);
    if (total <= 0) return 0;
    return Math.round(((weights[key] ?? 0) / total) * 100);
  }, []);

  const designed = useMemo(
    () =>
      focus.length > 0 ||
      Object.values(competencies).some((v) => v > 0) ||
      Object.values(formats).some((v) => v > 0) ||
      count !== "" ||
      notes.trim() !== "",
    [focus, competencies, formats, count, notes],
  );

  async function save() {
    setBusy(true);
    setError(null);
    setSaved(null);
    try {
      const res = await employerApi.saveMockDesign(workspace.id, jobId, {
        focus_skills: focus,
        competency_weights: competencies,
        format_mix: formats,
        question_count: count === "" ? null : Number(count),
        notes: notes.trim() || null,
      });
      setSaved(
        res.data.regenerate_required
          ? "Saved. The current questions still reflect the old design — regenerate when you are ready."
          : "Saved. The first generated interview will follow this design.",
      );
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save.");
    } finally {
      setBusy(false);
    }
  }

  if (error && data === null) {
    return <Tile accent={TRUST} hover={false}><p className="text-sm text-white/60">{error}</p></Tile>;
  }

  if (!data) {
    return <div className="space-y-4">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-44" />)}</div>;
  }

  return (
    <div className="space-y-5">
      <Tile accent={VIOLET} hover={false}>
        <Label>Interview design</Label>
        <p className="font-display mt-2 text-xl font-bold leading-tight md:text-2xl">
          Decide what this interview actually tests.
        </p>
        <p className="mt-2.5 max-w-2xl text-sm leading-relaxed text-white/55">
          Leave it all blank and the interview is designed from your job description. Set anything
          here and it is followed instead. Weights are ratios — they do not need to add up to
          anything.
        </p>
      </Tile>

      {/* Focus skills ------------------------------------------------- */}
      <Tile accent={TRUST} hover={false}>
        <Label>Focus skills</Label>
        <p className="mt-1.5 text-[13px] text-white/50">
          The questions lean on these before anything else on the JD.
        </p>
        <div className="mt-3 flex flex-wrap gap-1.5">
          {data.selectable_skills.map((skill) => {
            const on = focus.includes(skill);
            return (
              <button
                key={skill}
                type="button"
                aria-pressed={on}
                onClick={() => setFocus(on ? focus.filter((s) => s !== skill) : [...focus, skill])}
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
        {focus.length === 0 && (
          <p className="mt-3 text-[12px] text-white/35">
            None selected — the interview covers the JD&apos;s skills evenly.
          </p>
        )}
      </Tile>

      {/* Weighted dimensions ------------------------------------------ */}
      <div className="grid items-start gap-4 md:grid-cols-2 md:gap-5">
        <WeightGroup
          title="What to score"
          hint="How much each dimension is worth in the rubric."
          accent={VERIFY}
          options={data.competencies}
          weights={competencies}
          onChange={setCompetencies}
          share={share}
        />
        <WeightGroup
          title="Question mix"
          hint="Roughly how the questions are split by format."
          accent={AMBER}
          options={data.question_formats}
          weights={formats}
          onChange={setFormats}
          share={share}
        />
      </div>

      {/* Length + notes ------------------------------------------------ */}
      <Tile accent={TRUST} hover={false}>
        <div className="grid gap-5 md:grid-cols-3">
          <div>
            <Label>Questions</Label>
            <input
              type="number"
              min={4}
              max={20}
              value={count}
              onChange={(e) => setCount(e.target.value === "" ? "" : Number(e.target.value))}
              placeholder="Auto"
              className="mt-2 w-full rounded-2xl border border-white/[0.12] bg-white/[0.05] px-4 py-3 font-mono text-sm text-white outline-none placeholder:text-white/30 focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25"
            />
            <p className="mt-1.5 text-[12px] text-white/40">Blank lets the generator decide.</p>
          </div>
          <div className="md:col-span-2">
            <Label>Anything else this interview must cover</Label>
            <textarea
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              maxLength={800}
              placeholder="e.g. must be able to explain a migration they led end to end"
              className="mt-2 w-full resize-y rounded-2xl border border-white/[0.12] bg-white/[0.05] px-4 py-3 text-sm leading-relaxed text-white outline-none placeholder:text-white/30 focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25"
            />
          </div>
        </div>
      </Tile>

      <div className="flex flex-wrap items-center gap-3">
        <PrimaryButton onClick={save} disabled={busy}>
          {busy ? "Saving…" : "Save design"}
        </PrimaryButton>
        {designed && (
          <GhostButton
            onClick={() => {
              setFocus([]);
              setCompetencies({});
              setFormats({});
              setCount("");
              setNotes("");
            }}
          >
            Clear and use the JD
          </GhostButton>
        )}
        {data.has_mock && <Pill tone="amber">A mock already exists</Pill>}
      </div>

      {saved && <p className="text-[13px]" style={{ color: "#6ee7b7" }}>{saved}</p>}
      {error && <p className="text-[13px]" style={{ color: "#fca5a5" }}>{error}</p>}

      <p className="text-[12px] leading-relaxed text-white/35">
        Saving never rewrites an interview a candidate may be part-way through. Regenerate from the
        JD mock tab when you want the new design applied.
      </p>
    </div>
  );
}

function WeightGroup({
  title,
  hint,
  accent,
  options,
  weights,
  onChange,
  share,
}: {
  title: string;
  hint: string;
  accent: string;
  options: { key: string; label: string; hint: string }[];
  weights: Record<string, number>;
  onChange: (next: Record<string, number>) => void;
  share: (w: Record<string, number>, k: string) => number;
}) {
  const anySet = Object.values(weights).some((v) => v > 0);

  return (
    <Tile accent={accent} hover={false}>
      <Label>{title}</Label>
      <p className="mt-1.5 text-[13px] text-white/50">{hint}</p>

      <ul className="mt-4 space-y-3.5">
        {options.map((o) => {
          const value = weights[o.key] ?? 0;
          const pct = share(weights, o.key);
          return (
            <li key={o.key}>
              <div className="flex items-baseline justify-between gap-3">
                <label htmlFor={`w-${o.key}`} className="text-[13px] font-semibold text-white">
                  {o.label}
                </label>
                <span className="font-mono text-[11px]" style={{ color: value > 0 ? accent : "rgba(255,255,255,0.3)" }}>
                  {anySet ? `${pct}%` : "—"}
                </span>
              </div>
              <p className="mt-0.5 text-[11px] leading-snug text-white/35">{o.hint}</p>
              <input
                id={`w-${o.key}`}
                type="range"
                min={0}
                max={MAX_WEIGHT}
                step={1}
                value={value}
                onChange={(e) => {
                  const next = { ...weights };
                  const v = Number(e.target.value);
                  if (v === 0) delete next[o.key];
                  else next[o.key] = v;
                  onChange(next);
                }}
                className="mt-2 w-full accent-[#4d8ef7]"
                style={{ accentColor: accent }}
              />
            </li>
          );
        })}
      </ul>

      {!anySet && (
        <p className="mt-3 text-[12px] text-white/35">
          Nothing set — the generator picks its own balance.
        </p>
      )}
    </Tile>
  );
}
