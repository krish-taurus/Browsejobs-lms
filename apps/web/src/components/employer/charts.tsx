"use client";

/**
 * Employer chart surface.
 *
 * These used to be a separate hand-rolled set with their own colours. That
 * palette had a real defect: its blue and violet sat at ΔE 0.6 under
 * protanopia, so a red-blind viewer saw one colour where the chart claimed
 * two. Everything now delegates to the validated system in `viz/`, which was
 * stepped through the palette checker against the dark chart surface until
 * lightness band, chroma floor, CVD separation, normal-vision separation and
 * contrast all passed.
 *
 * The old export names are kept so the dashboard and JD pages did not need
 * rewriting around a new vocabulary — but they now resolve to validated hues.
 */

import { motion, useReducedMotion } from "framer-motion";
import type { ReactNode } from "react";
import { Funnel as VizFunnel, Histogram, Legend, Radial, TimeSeries } from "@/components/viz/Chart";
import { INK as VIZ_INK, SERIES, SURFACE } from "@/components/viz/tokens";

const EASE = [0.16, 1, 0.3, 1] as const;

/* Named slots, assigned in the validated fixed order. */
export const TRUST = SERIES[0];
export const VERIFY = SERIES[1];
export const AMBER = SERIES[2];
export const VIOLET = SERIES[3];
export const PINK = SERIES[4];
export const CYAN = SERIES[5];
/** Call sites that wanted a lighter or darker blue: both are the blue slot. */
export const SKY = SERIES[0];
export const DEEP = SERIES[0];
export const INK = VIZ_INK.primary;

export { Legend };

export function Ring({
  value,
  size = 112,
  color = TRUST,
  children,
}: {
  value: number;
  size?: number;
  stroke?: number;
  color?: string;
  track?: string;
  children?: ReactNode;
}) {
  return (
    <Radial value={value} size={size} color={color}>
      {children}
    </Radial>
  );
}

export function Funnel({
  stages,
}: {
  stages: { stage: string; label: string; count: number }[];
}) {
  return (
    <VizFunnel stages={stages.map((s) => ({ key: s.stage, label: s.label, count: s.count }))} />
  );
}

export function TrendChart({
  points,
  height = 130,
}: {
  points: { date: string; applications: number; graded: number }[];
  height?: number;
}) {
  return (
    <TimeSeries
      labels={points.map((p) => p.date)}
      series={[
        {
          key: "applications",
          label: "Applied",
          color: TRUST,
          values: points.map((p) => p.applications),
        },
        {
          key: "graded",
          label: "Graded",
          color: VERIFY,
          dashed: true,
          values: points.map((p) => p.graded),
        },
      ]}
      height={height}
    />
  );
}

export function ScoreHistogram({
  bands,
  threshold,
}: {
  bands: { band: string; floor: number; count: number }[];
  threshold?: number;
}) {
  return (
    <Histogram
      bands={bands.map((b) => ({ label: b.band, floor: b.floor, count: b.count }))}
      threshold={threshold}
      thresholdLabel={
        threshold !== undefined ? `applicants at or above your ${threshold}% threshold` : undefined
      }
    />
  );
}

/** Stacked per-JD pipeline bar. A 2px surface gap keeps segments readable. */
export function PipelineBar({
  segments,
}: {
  segments: { key: string; label: string; count: number; color: string }[];
}) {
  const reduce = useReducedMotion();
  const shown = segments.filter((s) => s.count > 0);
  const total = shown.reduce((a, s) => a + s.count, 0);

  if (total === 0) {
    return <div className="h-2 rounded-full" style={{ background: "rgba(255,255,255,0.06)" }} />;
  }

  return (
    <div className="flex h-2 gap-[2px] overflow-hidden rounded-full">
      {shown.map((s, i) => (
        <motion.div
          key={s.key}
          title={`${s.label}: ${s.count}`}
          initial={reduce ? false : { scaleX: 0 }}
          animate={{ scaleX: 1 }}
          transition={{ duration: 0.8, ease: EASE, delay: 0.05 * i }}
          style={{
            width: `${(s.count / total) * 100}%`,
            background: s.color,
            transformOrigin: "left",
          }}
        />
      ))}
    </div>
  );
}

export function SkillMeter({ pct, color = TRUST }: { pct: number; color?: string }) {
  const reduce = useReducedMotion();
  return (
    <div className="h-1.5 overflow-hidden rounded-full" style={{ background: SURFACE.line }}>
      <motion.div
        initial={reduce ? false : { width: 0 }}
        animate={{ width: `${pct}%` }}
        transition={{ duration: 0.9, ease: EASE }}
        className="h-full rounded-full"
        style={{ background: color }}
      />
    </div>
  );
}
