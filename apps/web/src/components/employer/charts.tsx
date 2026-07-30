"use client";

import { motion, useReducedMotion } from "framer-motion";
import type { ReactNode } from "react";

/**
 * Hand-rolled SVG/div chart primitives for the employer portal.
 *
 * No charting dependency: the landing page proves this house style
 * (animated strokeDashoffset rings, width-animated bars, polyline
 * sparklines) and it keeps the bundle small and the CSP clean. Every
 * chart states its numbers as text as well as shape, so meaning never
 * depends on colour alone.
 */

const EASE = [0.16, 1, 0.3, 1] as const;

export const INK = "#0a1220";
export const TRUST = "#1b6df0";
export const SKY = "#4d94ff";
export const DEEP = "#0e3fa9";
export const VERIFY = "#0ba860";
export const VIOLET = "#7c3aed";
export const AMBER = "#f5a623";

/* ------------------------------------------------------------------ */
/* Progress ring                                                       */
/* ------------------------------------------------------------------ */

export function Ring({
  value,
  size = 112,
  stroke = 10,
  color = TRUST,
  track = "#e7f1fe",
  children,
}: {
  value: number;
  size?: number;
  stroke?: number;
  color?: string;
  track?: string;
  children?: ReactNode;
}) {
  const reduce = useReducedMotion();
  const r = 50 - stroke / 2 - 2;
  const circumference = 2 * Math.PI * r;
  const offset = circumference * (1 - Math.max(0, Math.min(100, value)) / 100);

  return (
    <div className="relative" style={{ height: size, width: size }}>
      <svg viewBox="0 0 100 100" className="h-full w-full -rotate-90">
        <circle cx="50" cy="50" r={r} fill="none" stroke={track} strokeWidth={stroke} />
        <motion.circle
          cx="50"
          cy="50"
          r={r}
          fill="none"
          stroke={color}
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={circumference}
          initial={reduce ? { strokeDashoffset: offset } : { strokeDashoffset: circumference }}
          animate={{ strokeDashoffset: offset }}
          transition={{ duration: 1.4, ease: EASE, delay: 0.2 }}
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center">{children}</div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Funnel — horizontal bars with drop-off called out in text           */
/* ------------------------------------------------------------------ */

export function Funnel({
  stages,
}: {
  stages: { stage: string; label: string; count: number }[];
}) {
  const reduce = useReducedMotion();
  const top = Math.max(1, stages[0]?.count ?? 1);
  const shades = [DEEP, TRUST, TRUST, SKY, SKY, VERIFY, VERIFY];

  return (
    <ul className="space-y-3">
      {stages.map((s, i) => {
        const pct = Math.round((s.count / top) * 100);
        const prev = stages[i - 1];
        const conversion =
          prev && prev.count > 0 ? Math.round((s.count / prev.count) * 100) : null;

        return (
          <li key={s.stage}>
            <div className="flex items-baseline justify-between text-[11px]">
              <span className="font-semibold uppercase tracking-[0.16em] text-black/45">{s.label}</span>
              <span className="font-mono text-black/45">
                <span className="text-sm font-bold text-[#0a1220]">{s.count}</span>
                {conversion !== null && <span className="ml-2">{conversion}% carried</span>}
              </span>
            </div>
            <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-black/[0.06]">
              <motion.div
                initial={reduce ? false : { width: 0 }}
                animate={{ width: `${pct}%` }}
                transition={{ duration: 1, ease: EASE, delay: 0.1 + i * 0.08 }}
                className="h-full rounded-full"
                style={{ background: shades[i] ?? TRUST }}
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}

/* ------------------------------------------------------------------ */
/* Sparkline / trend — two series, distinguished by style not colour   */
/* ------------------------------------------------------------------ */

export function TrendChart({
  points,
  height = 90,
}: {
  points: { date: string; applications: number; graded: number }[];
  height?: number;
}) {
  const reduce = useReducedMotion();
  const w = 560;
  const h = height;
  const pad = 6;
  const max = Math.max(1, ...points.flatMap((p) => [p.applications, p.graded]));

  const path = (key: "applications" | "graded") =>
    points
      .map((p, i) => {
        const x = pad + (i / Math.max(1, points.length - 1)) * (w - pad * 2);
        const y = h - pad - (p[key] / max) * (h - pad * 2);
        return `${i === 0 ? "M" : "L"}${x.toFixed(1)},${y.toFixed(1)}`;
      })
      .join(" ");

  const areaPath = `${path("applications")} L${w - pad},${h - pad} L${pad},${h - pad} Z`;
  const last = points[points.length - 1];
  const lastX = w - pad;
  const lastY = h - pad - ((last?.applications ?? 0) / max) * (h - pad * 2);

  return (
    <div>
      <svg viewBox={`0 0 ${w} ${h}`} className="w-full" style={{ height }} role="img" aria-label="Applications and gradings over the last 14 days">
        {[0.25, 0.5, 0.75].map((f) => (
          <line key={f} x1={pad} x2={w - pad} y1={pad + f * (h - pad * 2)} y2={pad + f * (h - pad * 2)} stroke="#0a1220" strokeOpacity={0.05} strokeWidth={1} />
        ))}
        <motion.path
          d={areaPath}
          fill={TRUST}
          fillOpacity={0.08}
          initial={reduce ? false : { opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.8, ease: EASE, delay: 0.4 }}
        />
        <motion.path
          d={path("applications")}
          fill="none"
          stroke={TRUST}
          strokeWidth={2}
          strokeLinecap="round"
          initial={reduce ? false : { pathLength: 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 1.3, ease: EASE }}
        />
        <motion.path
          d={path("graded")}
          fill="none"
          stroke={VERIFY}
          strokeWidth={2}
          strokeDasharray="4 4"
          strokeLinecap="round"
          initial={reduce ? false : { pathLength: 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 1.3, ease: EASE, delay: 0.15 }}
        />
        <circle cx={lastX} cy={lastY} r={3.5} fill={TRUST} />
      </svg>
      <div className="mt-2 flex gap-4 text-[10px] font-semibold uppercase tracking-[0.16em] text-black/40">
        <span className="flex items-center gap-1.5">
          <span className="h-0.5 w-4 rounded-full" style={{ background: TRUST }} /> Applied
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-0.5 w-4 rounded-full border-t-2 border-dashed" style={{ borderColor: VERIFY }} /> Graded
        </span>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Score distribution histogram, with a threshold marker               */
/* ------------------------------------------------------------------ */

export function ScoreHistogram({
  bands,
  threshold,
}: {
  bands: { band: string; floor: number; count: number }[];
  threshold?: number;
}) {
  const reduce = useReducedMotion();
  const max = Math.max(1, ...bands.map((b) => b.count));

  return (
    <div>
      {/* Bars own a fixed-height track: percentage heights need a resolved
          parent height, so the labels live in their own row below. */}
      <div className="flex h-28 items-end gap-1.5">
        {bands.map((b, i) => {
          const above = threshold !== undefined && b.floor >= threshold;
          return (
            <motion.div
              key={b.floor}
              initial={reduce ? false : { height: 0 }}
              animate={{ height: `${Math.max(4, (b.count / max) * 100)}%` }}
              transition={{ duration: 0.8, ease: EASE, delay: 0.05 * i }}
              className="flex-1 rounded-t-md"
              style={{
                background: above
                  ? `linear-gradient(to top, ${TRUST}66, ${TRUST})`
                  : `linear-gradient(to top, ${INK}12, ${INK}24)`,
              }}
              title={`${b.band}: ${b.count}`}
            />
          );
        })}
      </div>
      <div className="mt-1.5 flex gap-1.5">
        {bands.map((b) => (
          <span key={b.floor} className="flex-1 text-center font-mono text-[9px] text-black/35">
            {b.floor}
          </span>
        ))}
      </div>
      {threshold !== undefined && (
        <p className="mt-2 text-[11px] text-black/45">
          <span className="font-mono font-semibold text-[#1b6df0]">
            {bands.filter((b) => b.floor >= threshold).reduce((a, b) => a + b.count, 0)}
          </span>{" "}
          applicants at or above your {threshold}% threshold.
        </p>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Stacked pipeline bar (per JD)                                       */
/* ------------------------------------------------------------------ */

export function PipelineBar({
  segments,
}: {
  segments: { key: string; label: string; count: number; color: string }[];
}) {
  const reduce = useReducedMotion();
  const total = segments.reduce((a, s) => a + s.count, 0);
  if (total === 0) {
    return <div className="h-2 rounded-full bg-black/[0.06]" />;
  }

  return (
    <div className="flex h-2 gap-0.5 overflow-hidden rounded-full">
      {segments
        .filter((s) => s.count > 0)
        .map((s, i) => (
          <motion.div
            key={s.key}
            title={`${s.label}: ${s.count}`}
            initial={reduce ? false : { scaleX: 0 }}
            animate={{ scaleX: 1 }}
            transition={{ duration: 0.9, ease: EASE, delay: 0.06 * i }}
            style={{ width: `${(s.count / total) * 100}%`, background: s.color, transformOrigin: "left" }}
          />
        ))}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Skill-match meter used in the talent pool                           */
/* ------------------------------------------------------------------ */

export function SkillMeter({ pct, color = TRUST }: { pct: number; color?: string }) {
  const reduce = useReducedMotion();
  return (
    <div className="h-1.5 overflow-hidden rounded-full bg-black/[0.07]">
      <motion.div
        initial={reduce ? false : { width: 0 }}
        animate={{ width: `${pct}%` }}
        transition={{ duration: 1, ease: EASE }}
        className="h-full rounded-full"
        style={{ background: color }}
      />
    </div>
  );
}
