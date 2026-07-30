"use client";

import { motion, useReducedMotion } from "framer-motion";
import { useId, useMemo, useRef, useState } from "react";
import type { ReactNode } from "react";
import { DUR, EASE, INK, SERIES, STATUS, SURFACE } from "./tokens";

/**
 * Chart primitives for the dark command deck.
 *
 * House rules enforced here rather than left to each call site:
 * - one y-axis, never two;
 * - a legend whenever there is more than one series, plus direct labels, so
 *   identity is never carried by colour alone;
 * - a hover layer by default — crosshair + tooltip on time series, per-mark
 *   tooltip on bars;
 * - recessive grid, thin marks, 4px rounded data-ends;
 * - every chart also states its numbers as text somewhere on the card.
 */

const fmt = (n: number): string => new Intl.NumberFormat("en-IN").format(n);

/* ------------------------------------------------------------------ */
/* Legend                                                              */
/* ------------------------------------------------------------------ */

export function Legend({
  items,
}: {
  items: { label: string; color: string; dashed?: boolean }[];
}) {
  return (
    <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1.5">
      {items.map((s) => (
        <li key={s.label} className="flex items-center gap-2">
          <span
            aria-hidden
            className="h-[3px] w-5 rounded-full"
            style={
              s.dashed
                ? { backgroundImage: `repeating-linear-gradient(90deg, ${s.color} 0 5px, transparent 5px 9px)` }
                : { background: s.color }
            }
          />
          <span className="text-[11px] font-medium" style={{ color: INK.secondary }}>
            {s.label}
          </span>
        </li>
      ))}
    </ul>
  );
}

/* ------------------------------------------------------------------ */
/* Time series — gradient stroke, live draw, crosshair tooltip         */
/* ------------------------------------------------------------------ */

export type SeriesSpec = {
  key: string;
  label: string;
  color: string;
  dashed?: boolean;
  values: number[];
};

export function TimeSeries({
  labels,
  series,
  height = 190,
  valueSuffix = "",
}: {
  labels: string[];
  series: SeriesSpec[];
  height?: number;
  valueSuffix?: string;
}) {
  const reduce = useReducedMotion();
  const uid = useId().replace(/:/g, "");
  const wrapRef = useRef<HTMLDivElement>(null);
  const [hover, setHover] = useState<number | null>(null);

  const W = 720;
  const H = height;
  const padL = 34;
  const padR = 12;
  const padT = 14;
  const padB = 26;

  const max = Math.max(1, ...series.flatMap((s) => s.values));
  const n = Math.max(1, labels.length - 1);

  const x = (i: number) => padL + (i / n) * (W - padL - padR);
  const y = (v: number) => H - padB - (v / max) * (H - padT - padB);

  const line = (vals: number[]) =>
    vals.map((v, i) => `${i === 0 ? "M" : "L"}${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(" ");

  // Four gridlines, labelled — the axis is recessive but the scale is legible.
  const ticks = useMemo(() => {
    const step = Math.max(1, Math.ceil(max / 3));
    return [0, step, step * 2, step * 3].filter((t) => t <= max * 1.35);
  }, [max]);

  function onMove(e: React.MouseEvent<HTMLDivElement>) {
    const rect = wrapRef.current?.getBoundingClientRect();
    if (!rect) return;
    const rel = ((e.clientX - rect.left) / rect.width) * W;
    const i = Math.round(((rel - padL) / (W - padL - padR)) * n);
    setHover(Math.max(0, Math.min(labels.length - 1, i)));
  }

  return (
    <div>
      <div
        ref={wrapRef}
        className="relative"
        onMouseMove={onMove}
        onMouseLeave={() => setHover(null)}
      >
        <svg
          viewBox={`0 0 ${W} ${H}`}
          className="w-full"
          style={{ height }}
          role="img"
          aria-label={`${series.map((s) => s.label).join(" and ")} over ${labels.length} points`}
        >
          <defs>
            {series.map((s, si) => (
              <linearGradient key={s.key} id={`${uid}-stroke-${si}`} x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor={s.color} stopOpacity={0.45} />
                <stop offset="100%" stopColor={s.color} stopOpacity={1} />
              </linearGradient>
            ))}
            <linearGradient id={`${uid}-fill`} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={series[0]?.color ?? SERIES[0]} stopOpacity={0.28} />
              <stop offset="100%" stopColor={series[0]?.color ?? SERIES[0]} stopOpacity={0} />
            </linearGradient>
            <filter id={`${uid}-glow`} x="-20%" y="-40%" width="140%" height="180%">
              <feGaussianBlur stdDeviation="4" result="b" />
              <feMerge>
                <feMergeNode in="b" />
                <feMergeNode in="SourceGraphic" />
              </feMerge>
            </filter>
          </defs>

          {ticks.map((t) => (
            <g key={t}>
              <line
                x1={padL}
                x2={W - padR}
                y1={y(t)}
                y2={y(t)}
                stroke={SURFACE.line}
                strokeWidth={1}
              />
              <text
                x={padL - 8}
                y={y(t) + 3.5}
                textAnchor="end"
                fontSize={10}
                fontFamily="var(--font-mono, monospace)"
                fill={INK.faint}
              >
                {t}
              </text>
            </g>
          ))}

          {/* Area under the primary series only — a second fill would muddy it. */}
          <motion.path
            d={`${line(series[0]?.values ?? [])} L${x(n)},${H - padB} L${padL},${H - padB} Z`}
            fill={`url(#${uid}-fill)`}
            initial={reduce ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: DUR.slow, ease: EASE, delay: 0.3 }}
          />

          {series.map((s, si) => (
            <motion.path
              key={s.key}
              d={line(s.values)}
              fill="none"
              stroke={`url(#${uid}-stroke-${si})`}
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeDasharray={s.dashed ? "5 5" : undefined}
              filter={si === 0 ? `url(#${uid}-glow)` : undefined}
              initial={reduce ? false : { pathLength: 0 }}
              animate={{ pathLength: 1 }}
              transition={{ duration: DUR.draw, ease: EASE, delay: si * 0.12 }}
            />
          ))}

          {hover !== null && (
            <g>
              <line
                x1={x(hover)}
                x2={x(hover)}
                y1={padT}
                y2={H - padB}
                stroke={SURFACE.lineStrong}
                strokeWidth={1}
              />
              {series.map((s) => (
                <circle
                  key={s.key}
                  cx={x(hover)}
                  cy={y(s.values[hover] ?? 0)}
                  r={4.5}
                  fill={s.color}
                  stroke={SURFACE.card}
                  strokeWidth={2}
                />
              ))}
            </g>
          )}
        </svg>

        {hover !== null && (
          <div
            className="pointer-events-none absolute top-1 z-10 min-w-[132px] -translate-x-1/2 rounded-xl border p-2.5 text-left shadow-2xl"
            style={{
              left: `${((x(hover) / W) * 100).toFixed(2)}%`,
              background: SURFACE.raised,
              borderColor: SURFACE.lineStrong,
            }}
          >
            <p className="font-mono text-[10px]" style={{ color: INK.muted }}>
              {labels[hover]}
            </p>
            {series.map((s) => (
              <p key={s.key} className="mt-1 flex items-center gap-2 text-[11px]">
                <span
                  aria-hidden
                  className="h-2 w-2 shrink-0 rounded-full"
                  style={{ background: s.color }}
                />
                <span style={{ color: INK.secondary }}>{s.label}</span>
                <span className="ml-auto font-mono font-semibold" style={{ color: INK.primary }}>
                  {fmt(s.values[hover] ?? 0)}
                  {valueSuffix}
                </span>
              </p>
            ))}
          </div>
        )}
      </div>

      {series.length > 1 && (
        <Legend items={series.map((s) => ({ label: s.label, color: s.color, dashed: s.dashed }))} />
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Funnel — magnitude by length, carry-over stated as text             */
/* ------------------------------------------------------------------ */

export function Funnel({
  stages,
}: {
  stages: { key: string; label: string; count: number }[];
}) {
  const reduce = useReducedMotion();
  const top = Math.max(1, stages[0]?.count ?? 1);

  return (
    <ul className="space-y-2.5">
      {stages.map((s, i) => {
        const pct = (s.count / top) * 100;
        const prev = stages[i - 1];
        const carried = prev && prev.count > 0 ? Math.round((s.count / prev.count) * 100) : null;

        return (
          <li key={s.key}>
            <div className="flex items-baseline justify-between gap-3">
              <span className="text-[11px] font-medium" style={{ color: INK.secondary }}>
                {s.label}
              </span>
              <span className="flex items-baseline gap-2 font-mono text-[11px]">
                <span className="text-[13px] font-semibold" style={{ color: INK.primary }}>
                  {fmt(s.count)}
                </span>
                {carried !== null && <span style={{ color: INK.muted }}>{carried}% carried</span>}
              </span>
            </div>
            <div
              className="mt-1.5 h-2 overflow-hidden rounded-full"
              style={{ background: "rgba(255,255,255,0.05)" }}
            >
              <motion.div
                initial={reduce ? false : { width: 0 }}
                animate={{ width: `${pct}%` }}
                transition={{ duration: DUR.slow, ease: EASE, delay: 0.06 * i }}
                className="h-full rounded-full"
                // One hue, stepping darker down the funnel: this is magnitude,
                // not identity, so a sequential ramp is correct here.
                style={{
                  background: `linear-gradient(90deg, ${SERIES[0]}b0, ${SERIES[0]})`,
                  opacity: 1 - i * 0.1,
                }}
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}

/* ------------------------------------------------------------------ */
/* Histogram — distribution with a threshold marker                    */
/* ------------------------------------------------------------------ */

export function Histogram({
  bands,
  threshold,
  thresholdLabel,
}: {
  bands: { label: string; floor: number; count: number }[];
  threshold?: number;
  thresholdLabel?: string;
}) {
  const reduce = useReducedMotion();
  const [hover, setHover] = useState<number | null>(null);
  const max = Math.max(1, ...bands.map((b) => b.count));

  return (
    <div>
      <div className="relative flex h-32 items-end gap-1.5">
        {bands.map((b, i) => {
          const above = threshold !== undefined && b.floor >= threshold;
          return (
            <div
              key={b.floor}
              className="relative flex h-full flex-1 items-end"
              onMouseEnter={() => setHover(i)}
              onMouseLeave={() => setHover(null)}
            >
              <motion.div
                initial={reduce ? false : { height: 0 }}
                animate={{ height: `${Math.max(3, (b.count / max) * 100)}%` }}
                transition={{ duration: DUR.base, ease: EASE, delay: 0.03 * i }}
                className="w-full rounded-t-[4px]"
                style={{
                  background: above
                    ? `linear-gradient(180deg, ${SERIES[0]}, ${SERIES[0]}55)`
                    : "rgba(244,247,251,0.14)",
                }}
              />
              {hover === i && (
                <div
                  className="pointer-events-none absolute -top-1 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-lg border px-2 py-1"
                  style={{ background: SURFACE.raised, borderColor: SURFACE.lineStrong }}
                >
                  <span className="font-mono text-[10px]" style={{ color: INK.primary }}>
                    {b.label}: {b.count}
                  </span>
                </div>
              )}
            </div>
          );
        })}
      </div>
      <div className="mt-1.5 flex gap-1.5">
        {bands.map((b) => (
          <span
            key={b.floor}
            className="flex-1 text-center font-mono text-[9px]"
            style={{ color: INK.faint }}
          >
            {b.floor}
          </span>
        ))}
      </div>
      {threshold !== undefined && (
        <p className="mt-3 text-[12px]" style={{ color: INK.secondary }}>
          <span className="font-mono font-semibold" style={{ color: SERIES[0] }}>
            {bands.filter((b) => b.floor >= threshold).reduce((a, b) => a + b.count, 0)}
          </span>{" "}
          {thresholdLabel ?? `at or above ${threshold}`}
        </p>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Radial progress — one number, drawn                                 */
/* ------------------------------------------------------------------ */

export function Radial({
  value,
  size = 132,
  stroke = 9,
  color = SERIES[0],
  children,
}: {
  value: number;
  size?: number;
  stroke?: number;
  color?: string;
  children?: ReactNode;
}) {
  const reduce = useReducedMotion();
  const uid = useId().replace(/:/g, "");
  const r = 50 - stroke / 2 - 1;
  const circ = 2 * Math.PI * r;
  const clamped = Math.max(0, Math.min(100, value));

  return (
    <div className="relative shrink-0" style={{ height: size, width: size }}>
      <svg viewBox="0 0 100 100" className="h-full w-full -rotate-90">
        <defs>
          <linearGradient id={`${uid}-g`} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor={color} stopOpacity={0.5} />
            <stop offset="100%" stopColor={color} />
          </linearGradient>
        </defs>
        <circle
          cx="50"
          cy="50"
          r={r}
          fill="none"
          stroke="rgba(255,255,255,0.07)"
          strokeWidth={stroke}
        />
        <motion.circle
          cx="50"
          cy="50"
          r={r}
          fill="none"
          stroke={`url(#${uid}-g)`}
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={circ}
          initial={reduce ? false : { strokeDashoffset: circ }}
          animate={{ strokeDashoffset: circ * (1 - clamped / 100) }}
          transition={{ duration: DUR.draw, ease: EASE, delay: 0.15 }}
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center text-center">{children}</div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Status dot — state always carries a label, never colour alone       */
/* ------------------------------------------------------------------ */

export function StatusChip({
  tone,
  children,
}: {
  tone: keyof typeof STATUS;
  children: ReactNode;
}) {
  const color = STATUS[tone];
  return (
    <span
      className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.1em]"
      style={{ background: `${color}1f`, color }}
    >
      <span aria-hidden className="h-1.5 w-1.5 rounded-full" style={{ background: color }} />
      {children}
    </span>
  );
}
