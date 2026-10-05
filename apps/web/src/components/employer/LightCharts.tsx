"use client";

/**
 * Light-surface chart set — dashboard-only.
 *
 * The employer portal's main chart primitives (`@/components/viz/Chart`) are
 * validated specifically against a dark card (#0a0f1c): their gridlines, bar
 * tracks and axis text are all near-white at low opacity, which is illegible
 * once dropped onto a white card. That system is shared by a dozen other
 * screens (pipeline, jobs, the candidate portal) that still want the dark
 * look, so it is left alone rather than retextured out from under them.
 *
 * These three are a separate, self-contained set for the one place that
 * wants its charts drawn directly on white — same prop shapes as their dark
 * counterparts in `employer/charts.tsx`, so a call site can swap the import
 * without reshaping its data.
 */

import { motion, useReducedMotion } from "framer-motion";
import { useId, useMemo, useRef, useState } from "react";

const TRUST = "#1877f2";
const VIOLET = "#7c4fe0";
const LINE = "#eceef1";
const AXIS = "#8a8d91";

const fmt = (n: number) => new Intl.NumberFormat("en-IN").format(n);

export function LightTrendChart({
  points,
  height = 190,
}: {
  points: { date: string; applications: number; graded: number }[];
  height?: number;
}) {
  const reduce = useReducedMotion();
  const uid = useId().replace(/:/g, "");
  const wrapRef = useRef<HTMLDivElement>(null);
  const [hover, setHover] = useState<number | null>(null);

  const W = 720;
  const H = height;
  const padL = 28;
  const padR = 10;
  const padT = 14;
  const padB = 24;

  const max = Math.max(1, ...points.map((p) => p.applications), ...points.map((p) => p.graded));
  const n = Math.max(1, points.length - 1);

  const x = (i: number) => padL + (i / n) * (W - padL - padR);
  const y = (v: number) => H - padB - (v / max) * (H - padT - padB);

  const line = (vals: number[]) =>
    vals.map((v, i) => `${i === 0 ? "M" : "L"}${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(" ");

  const ticks = useMemo(() => {
    const step = Math.max(1, Math.ceil(max / 3));
    return [0, step, step * 2, step * 3].filter((t) => t <= max * 1.35);
  }, [max]);

  function onMove(e: React.MouseEvent<HTMLDivElement>) {
    const rect = wrapRef.current?.getBoundingClientRect();
    if (!rect) return;
    const rel = ((e.clientX - rect.left) / rect.width) * W;
    const i = Math.round(((rel - padL) / (W - padL - padR)) * n);
    setHover(Math.max(0, Math.min(points.length - 1, i)));
  }

  const applied = points.map((p) => p.applications);
  const graded = points.map((p) => p.graded);

  return (
    <div>
      <div ref={wrapRef} className="relative" onMouseMove={onMove} onMouseLeave={() => setHover(null)}>
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" style={{ height }} role="img" aria-label="Applications and graded interviews over time">
          <defs>
            <linearGradient id={`${uid}-fill`} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={TRUST} stopOpacity={0.16} />
              <stop offset="100%" stopColor={TRUST} stopOpacity={0} />
            </linearGradient>
          </defs>

          {ticks.map((t) => (
            <g key={t}>
              <line x1={padL} x2={W - padR} y1={y(t)} y2={y(t)} stroke={LINE} strokeWidth={1} />
              <text x={padL - 8} y={y(t) + 3.5} textAnchor="end" fontSize={10} fontFamily="var(--font-mono, monospace)" fill={AXIS}>
                {t}
              </text>
            </g>
          ))}

          <motion.path
            d={`${line(applied)} L${x(n)},${H - padB} L${padL},${H - padB} Z`}
            fill={`url(#${uid}-fill)`}
            initial={reduce ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.9, delay: 0.2 }}
          />

          <motion.path
            d={line(graded)}
            fill="none"
            stroke={VIOLET}
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeDasharray="6 5"
            initial={reduce ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.9, delay: 0.3 }}
          />

          <motion.path
            d={line(applied)}
            fill="none"
            stroke={TRUST}
            strokeWidth={2.5}
            strokeLinecap="round"
            strokeLinejoin="round"
            initial={reduce ? false : { pathLength: 0 }}
            animate={{ pathLength: 1 }}
            transition={{ duration: 1.1, delay: 0 }}
          />

          {hover !== null && (
            <g>
              <line x1={x(hover)} x2={x(hover)} y1={padT} y2={H - padB} stroke="#dfe3ea" strokeWidth={1} />
              <circle cx={x(hover)} cy={y(applied[hover] ?? 0)} r={4} fill={TRUST} stroke="#fff" strokeWidth={2} />
              <circle cx={x(hover)} cy={y(graded[hover] ?? 0)} r={4} fill={VIOLET} stroke="#fff" strokeWidth={2} />
            </g>
          )}
        </svg>

        {hover !== null && (
          <div
            className="pointer-events-none absolute top-1 z-10 min-w-[132px] -translate-x-1/2 rounded-xl border border-[#e4e6eb] bg-white p-2.5 text-left shadow-[0_10px_30px_-10px_rgba(0,0,0,0.25)]"
            style={{ left: `${((x(hover) / W) * 100).toFixed(2)}%` }}
          >
            <p className="font-mono text-[10px] text-[#8a8d91]">{points[hover]?.date}</p>
            <p className="mt-1 flex items-center gap-2 text-[11px]">
              <span aria-hidden className="h-2 w-2 shrink-0 rounded-full" style={{ background: TRUST }} />
              <span className="text-[#65676b]">Applied</span>
              <span className="ml-auto font-mono font-semibold text-[#050505]">{fmt(applied[hover] ?? 0)}</span>
            </p>
            <p className="mt-1 flex items-center gap-2 text-[11px]">
              <span aria-hidden className="h-2 w-2 shrink-0 rounded-full" style={{ background: VIOLET }} />
              <span className="text-[#65676b]">Graded</span>
              <span className="ml-auto font-mono font-semibold text-[#050505]">{fmt(graded[hover] ?? 0)}</span>
            </p>
          </div>
        )}
      </div>

      <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1.5">
        <li className="flex items-center gap-2">
          <span aria-hidden className="h-[3px] w-5 rounded-full" style={{ background: TRUST }} />
          <span className="text-[11px] font-medium text-[#65676b]">Applications</span>
        </li>
        <li className="flex items-center gap-2">
          <span aria-hidden className="h-[3px] w-5 rounded-full" style={{ backgroundImage: `repeating-linear-gradient(90deg, ${VIOLET} 0 5px, transparent 5px 9px)` }} />
          <span className="text-[11px] font-medium text-[#65676b]">Graded</span>
        </li>
      </ul>
    </div>
  );
}

export function LightFunnel({
  stages,
}: {
  stages: { stage: string; label: string; count: number }[];
}) {
  const reduce = useReducedMotion();
  const top = Math.max(1, stages[0]?.count ?? 1);

  return (
    <ul className="space-y-3">
      {stages.map((s, i) => {
        const pct = (s.count / top) * 100;
        const prev = stages[i - 1];
        const carried = prev && prev.count > 0 ? Math.round((s.count / prev.count) * 100) : null;

        return (
          <li key={s.stage}>
            <div className="flex items-baseline justify-between gap-3">
              <span className="text-[13px] font-medium text-[#050505]">{s.label}</span>
              <span className="flex items-baseline gap-2 font-mono text-[12px]">
                <span className="text-[13px] font-semibold text-[#050505]">{fmt(s.count)}</span>
                {carried !== null && <span className="text-[#8a8d91]">{carried}%</span>}
              </span>
            </div>
            <div className="mt-1.5 h-2.5 overflow-hidden rounded-full bg-[#eceef1]">
              <motion.div
                initial={reduce ? false : { width: 0 }}
                animate={{ width: `${Math.max(pct, s.count > 0 ? 4 : 0)}%` }}
                transition={{ duration: 0.9, delay: 0.06 * i }}
                className="h-full rounded-full"
                style={{ background: TRUST, opacity: 1 - i * 0.09 }}
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}

export function LightHistogram({
  bands,
  threshold,
}: {
  bands: { band: string; floor: number; count: number }[];
  threshold?: number;
}) {
  const reduce = useReducedMotion();
  const [hover, setHover] = useState<number | null>(null);
  const max = Math.max(1, ...bands.map((b) => b.count));
  const atOrAbove = threshold !== undefined ? bands.filter((b) => b.floor >= threshold).reduce((a, b) => a + b.count, 0) : null;

  return (
    <div>
      <div className="relative flex h-32 items-end gap-1.5">
        {bands.map((b, i) => {
          const above = threshold !== undefined && b.floor >= threshold;
          return (
            <div key={b.floor} className="relative flex h-full flex-1 items-end" onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
              <motion.div
                initial={reduce ? false : { height: 0 }}
                animate={{ height: `${Math.max(3, (b.count / max) * 100)}%` }}
                transition={{ duration: 0.7, delay: 0.03 * i }}
                className="w-full rounded-t-[4px]"
                style={{ background: above ? TRUST : "#dfe3ea" }}
              />
              {hover === i && (
                <div className="pointer-events-none absolute -top-1 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-lg border border-[#e4e6eb] bg-white px-2 py-1 shadow-[0_10px_30px_-10px_rgba(0,0,0,0.25)]">
                  <span className="font-mono text-[10px] text-[#050505]">{b.band}: {b.count}</span>
                </div>
              )}
            </div>
          );
        })}
      </div>
      <div className="mt-1.5 flex gap-1.5">
        {bands.map((b) => (
          <span key={b.floor} className="flex-1 text-center font-mono text-[9px] text-[#8a8d91]">{b.floor}</span>
        ))}
      </div>
      {threshold !== undefined && (
        <p className="mt-3 text-[12px] text-[#65676b]">
          <span className="font-mono font-semibold text-[#1877f2]">{atOrAbove}</span> applicants at or above your {threshold}% threshold
        </p>
      )}
    </div>
  );
}
