"use client";

import Link from "next/link";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { type MasteryRow, useCoach } from "@/lib/coach";

/* ---- PRI ring: an SVG progress ring that draws in on mount ---- */

const R = 52;
const CIRC = 2 * Math.PI * R;

function PriRing({ pri }: { pri: number }) {
  const reduce = useReducedMotion();
  const offset = CIRC * (1 - Math.max(0, Math.min(100, pri)) / 100);

  return (
    <div className="relative grid h-32 w-32 place-items-center">
      <svg viewBox="0 0 120 120" className="h-32 w-32 -rotate-90">
        <circle cx="60" cy="60" r={R} fill="none" stroke="var(--color-line)" strokeWidth="10" />
        <motion.circle
          cx="60"
          cy="60"
          r={R}
          fill="none"
          stroke="var(--color-trust)"
          strokeWidth="10"
          strokeLinecap="round"
          strokeDasharray={CIRC}
          initial={reduce ? { strokeDashoffset: offset } : { strokeDashoffset: CIRC }}
          animate={{ strokeDashoffset: offset }}
          transition={{ duration: durations.slower, ease }}
        />
      </svg>
      <div className="absolute grid place-items-center text-center">
        <span className="mono text-3xl font-bold text-ink">{pri}</span>
        <span className="mono text-[10px] uppercase tracking-widest text-muted">PRI</span>
      </div>
    </div>
  );
}

/* ---- Trajectory: a 14-day PRI sparkline ---- */

function Sparkline({ points }: { points: { on: string; pri: number }[] }) {
  if (points.length < 2) {
    return <p className="text-xs text-muted">Your PRI trend appears once you have a few days of activity.</p>;
  }
  const w = 200;
  const h = 40;
  const xs = points.map((_, i) => (i / (points.length - 1)) * w);
  const ys = points.map((p) => h - (Math.max(0, Math.min(100, p.pri)) / 100) * h);
  const d = xs.map((x, i) => `${i === 0 ? "M" : "L"}${x.toFixed(1)},${ys[i].toFixed(1)}`).join(" ");
  const last = points[points.length - 1].pri;
  const first = points[0].pri;
  const delta = last - first;

  return (
    <div>
      <svg viewBox={`0 0 ${w} ${h}`} className="h-10 w-full" preserveAspectRatio="none">
        <path d={d} fill="none" stroke="var(--color-trust)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
      <p className="mono mt-1 text-[11px] text-muted">
        {delta === 0 ? "steady" : delta > 0 ? `▲ +${delta}` : `▼ ${delta}`} over {points.length} days
      </p>
    </div>
  );
}

/* ---- Mastery bars ---- */

const BAND_BAR: Record<MasteryRow["band"], string> = {
  weak: "bg-warn",
  developing: "bg-trust",
  strong: "bg-verify",
};

function MasteryBars({ rows }: { rows: MasteryRow[] }) {
  if (rows.length === 0) {
    return <p className="text-sm text-muted">No modules yet — your mastery map fills in as you complete topics.</p>;
  }
  return (
    <div className="space-y-3">
      {rows.map((m) => (
        <div key={m.module_id}>
          <div className="flex items-baseline justify-between gap-3">
            <span className="truncate text-sm text-ink">{m.name}</span>
            <span className="mono text-xs text-muted">{m.pct}%</span>
          </div>
          <div className="mt-1 h-2 overflow-hidden rounded-full bg-line">
            <div className={`h-full rounded-full ${BAND_BAR[m.band]}`} style={{ width: `${m.pct}%` }} />
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---- The panel ---- */

export function CoachPanel() {
  const { coach, loading, error } = useCoach();

  if (loading) {
    return (
      <div className="mt-8 space-y-4">
        <div className="shimmer h-40 rounded-2xl" />
        <div className="shimmer h-28 rounded-2xl" />
      </div>
    );
  }

  if (error || !coach) {
    return (
      <div className="mt-8 rounded-2xl border border-line bg-white p-6">
        <p className="kicker text-trust">Coach</p>
        <p className="mt-2 text-sm text-muted">
          Your coach panel will appear here once your batch is active and you have some learning activity.
        </p>
      </div>
    );
  }

  const na = coach.next_action;

  return (
    <div className="mt-8 space-y-6">
      {/* Next Best Action — the single loudest element (CLAUDE.md). */}
      <div className="rounded-2xl bg-ink p-7 text-white shadow-lg">
        <p className="kicker text-sky/70">Next best action</p>
        <h2 className="display mt-2 text-xl">{na.title}</h2>
        <p className="mt-1 text-sky/70">{na.detail}</p>
        {na.href && (
          <Link
            href={na.href}
            className="mt-5 inline-block rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-ink transition-transform hover:-translate-y-0.5"
          >
            Let’s go
          </Link>
        )}
      </div>

      {/* PRI ring + trajectory + stat band */}
      <div className="grid gap-4 sm:grid-cols-[auto_1fr]">
        <div className="grid place-items-center rounded-2xl border border-line bg-white p-6">
          <PriRing pri={coach.pri} />
        </div>
        <div className="rounded-2xl border border-line bg-white p-6">
          <p className="kicker text-trust">Placement readiness trend</p>
          <div className="mt-3">
            <Sparkline points={coach.trajectory} />
          </div>
          <div className="mt-4 grid grid-cols-2 gap-4">
            <div>
              <div className="mono text-2xl text-ink">{coach.engagement}</div>
              <p className="mt-0.5 text-xs text-muted">Engagement</p>
            </div>
            <div>
              <div className="mono text-2xl text-ink">
                {coach.streak_days}
                <span className="ml-1 text-sm text-muted">day{coach.streak_days === 1 ? "" : "s"}</span>
              </div>
              <p className="mt-0.5 text-xs text-muted">Current streak</p>
            </div>
          </div>
        </div>
      </div>

      {/* Needs-work (dominant) + went-well */}
      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-trust/30 bg-sky/40 p-6 md:order-1">
          <p className="kicker text-deep">Focus here next</p>
          {coach.needs_work.length === 0 ? (
            <p className="mt-3 text-sm text-muted">Nothing flagged — keep the momentum going.</p>
          ) : (
            <ul className="mt-3 space-y-2">
              {coach.needs_work.map((w, i) => (
                <li key={i} className="flex gap-2 text-sm text-ink">
                  <span className="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-trust" />
                  {w}
                </li>
              ))}
            </ul>
          )}
        </div>
        <div className="rounded-2xl border border-verify/25 bg-verify-bg p-6 md:order-2">
          <p className="kicker text-verify">Going well</p>
          {coach.went_well.length === 0 ? (
            <p className="mt-3 text-sm text-muted">Wins show up here as you complete topics.</p>
          ) : (
            <ul className="mt-3 space-y-2">
              {coach.went_well.map((w, i) => (
                <li key={i} className="flex gap-2 text-sm text-ink">
                  <span className="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-verify" />
                  {w}
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      {/* Mastery map */}
      <div className="rounded-2xl border border-line bg-white p-6">
        <p className="kicker text-trust">Mastery map</p>
        <div className="mt-4">
          <MasteryBars rows={coach.mastery} />
        </div>
      </div>

      {/* Evidence-backed insights from anonymised cohort data (PRD §6.21) */}
      {coach.insights.length > 0 && (
        <div className="rounded-2xl border border-trust/25 bg-sky/40 p-6">
          <p className="kicker text-trust">What the data says</p>
          <ul className="mt-3 space-y-2">
            {coach.insights.map((insight, i) => (
              <li key={i} className="flex gap-2 text-sm text-ink">
                <span className="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-trust" />
                {insight}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
