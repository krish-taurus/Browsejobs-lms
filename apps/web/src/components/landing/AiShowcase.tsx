"use client";

import { useEffect, useRef, useState } from "react";
import { motion, AnimatePresence, useReducedMotion, useScroll, useMotionValueEvent } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { Kicker } from "@/components/brand/Kicker";

/**
 * S5 — AI-assisted training & deployment. Apple-style pinned sequence: as you scroll the
 * section, the product visual morphs through five real-UI micro-demos while the copy
 * cross-fades beside it. Pinned only on desktop; mobile and reduced-motion get a clean
 * stacked version with static high-fidelity frames.
 */

const STATES = [
  { kicker: "AI personal coach", title: "A coach that reads your data, not a dashboard you read", body: "Your PRI, what went well, what needs work — and exactly one next best action, every day." },
  { kicker: "Live classes + recordings", title: "Every class live. Every class yours, forever", body: "Instructor-led sessions, then a searchable recordings library with your watch-progress kept." },
  { kicker: "AI voice mock interviewer", title: "Interviewed by AI, scored on real data", body: "A spoken mock, a competency scorecard, and the gap between you and what real interviews actually weight." },
  { kicker: "ATS CV generator", title: "A CV that gets past the machine and the manager", body: "Assembled from your real projects, scored against the ATS, brand-free and employer-ready." },
  { kicker: "Deployment", title: "Matched, tracked, placed", body: "Jobs matched to your profile, an application tracker, and a placement team that works it until you accept." },
] as const;

export function AiShowcase() {
  const reduce = useReducedMotion();
  const ref = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);

  const { scrollYProgress } = useScroll({ target: ref, offset: ["start start", "end end"] });
  useMotionValueEvent(scrollYProgress, "change", (p) => {
    if (reduce) return;
    setActive(Math.max(0, Math.min(STATES.length - 1, Math.floor(p * STATES.length))));
  });

  const Head = (
    <div className="mx-auto max-w-6xl px-5 pt-20 md:pt-28">
      <Kicker>How we train &amp; deploy you</Kicker>
      <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
        AI at every step. Humans teaching and verifying.
      </h2>
    </div>
  );

  // Mobile / reduced-motion: stacked, static frames.
  if (reduce) {
    return (
      <section className="bg-white">
        {Head}
        <div className="mx-auto max-w-6xl space-y-16 px-5 py-16">
          {STATES.map((s, i) => (
            <div key={i} className="grid items-center gap-8 md:grid-cols-2">
              <Copy state={s} />
              <Mock stage={i} animate={false} />
            </div>
          ))}
        </div>
      </section>
    );
  }

  return (
    <section className="bg-white">
      {Head}

      {/* Mobile: stacked */}
      <div className="mx-auto max-w-6xl space-y-16 px-5 py-14 md:hidden">
        {STATES.map((s, i) => (
          <div key={i} className="space-y-6">
            <Copy state={s} />
            <Mock stage={i} animate />
          </div>
        ))}
      </div>

      {/* Desktop: pinned sequence */}
      <div ref={ref} className="relative hidden md:block" style={{ height: `${STATES.length * 100}vh` }}>
        <div className="sticky top-0 flex h-screen items-center">
          <div className="mx-auto grid w-full max-w-6xl grid-cols-2 items-center gap-14 px-5">
            <div className="relative min-h-[160px]">
              <AnimatePresence mode="wait">
                <motion.div
                  key={active}
                  initial={{ opacity: 0, y: 14 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -14 }}
                  transition={{ duration: durations.slow, ease }}
                >
                  <Copy state={STATES[active]} />
                </motion.div>
              </AnimatePresence>

              <div className="mt-10 flex gap-2">
                {STATES.map((_, i) => (
                  <span key={i} className={`h-1 rounded-full transition-all duration-500 ${i === active ? "w-8 bg-trust" : "w-4 bg-line"}`} />
                ))}
              </div>
            </div>

            <div className="relative">
              <AnimatePresence mode="wait">
                <motion.div
                  key={active}
                  initial={{ opacity: 0, scale: 0.98 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: 0.98 }}
                  transition={{ duration: durations.slow, ease }}
                >
                  <Mock stage={active} animate />
                </motion.div>
              </AnimatePresence>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function Copy({ state }: { state: (typeof STATES)[number] }) {
  return (
    <div>
      <Kicker>{state.kicker}</Kicker>
      <h3 className="display mt-3 text-2xl text-ink md:text-4xl">{state.title}</h3>
      <p className="mt-4 max-w-md text-ink2/70">{state.body}</p>
    </div>
  );
}

/* ---------- the product frame + five micro-demos ---------- */

function Mock({ stage, animate }: { stage: number; animate: boolean }) {
  return (
    <div className="rounded-[22px] border border-line bg-paper p-5 shadow-soft">
      <div className="mb-4 flex items-center gap-1.5">
        <span className="h-2.5 w-2.5 rounded-full bg-line" />
        <span className="h-2.5 w-2.5 rounded-full bg-line" />
        <span className="h-2.5 w-2.5 rounded-full bg-line" />
      </div>
      {stage === 0 && <CoachMock animate={animate} />}
      {stage === 1 && <RecordingsMock animate={animate} />}
      {stage === 2 && <VoiceMock animate={animate} />}
      {stage === 3 && <CvMock animate={animate} />}
      {stage === 4 && <DeployMock animate={animate} />}
    </div>
  );
}

function CoachMock({ animate }: { animate: boolean }) {
  const score = 72;
  const C = 2 * Math.PI * 34;
  return (
    <div className="rounded-[14px] bg-white p-5">
      <div className="flex items-center gap-5">
        <div className="relative h-24 w-24 shrink-0">
          <svg viewBox="0 0 80 80" className="h-24 w-24 -rotate-90">
            <circle cx="40" cy="40" r="34" fill="none" stroke="var(--bj-line)" strokeWidth="8" />
            <motion.circle
              cx="40" cy="40" r="34" fill="none" stroke="var(--bj-trust)" strokeWidth="8" strokeLinecap="round"
              strokeDasharray={C}
              initial={{ strokeDashoffset: animate ? C : C * (1 - score / 100) }}
              animate={{ strokeDashoffset: C * (1 - score / 100) }}
              transition={{ duration: 1.1, ease }}
            />
          </svg>
          <div className="absolute inset-0 grid place-items-center">
            <span className="display text-xl text-ink">{score}</span>
          </div>
        </div>
        <div className="flex-1 space-y-2">
          <div className="rounded-lg bg-verify-bg px-3 py-2">
            <p className="mono text-[10px] uppercase tracking-widest text-verify">Went well</p>
            <p className="text-xs text-ink">SQL joins &amp; window functions</p>
          </div>
          <div className="rounded-lg bg-warn/10 px-3 py-2">
            <p className="mono text-[10px] uppercase tracking-widest text-warn">Needs work</p>
            <p className="text-xs text-ink">Spark performance tuning</p>
          </div>
        </div>
      </div>
      <motion.div
        className="mt-4 flex items-center justify-between rounded-full bg-trust px-4 py-2.5 text-white shadow-[0_0_20px_rgba(27,109,240,0.4)]"
        animate={animate ? { boxShadow: ["0 0 12px rgba(27,109,240,0.3)", "0 0 24px rgba(27,109,240,0.55)", "0 0 12px rgba(27,109,240,0.3)"] } : undefined}
        transition={{ duration: 2.2, ease, repeat: Infinity }}
      >
        <span className="text-sm font-semibold">Next: one Spark mock before Thursday</span>
        <span>→</span>
      </motion.div>
    </div>
  );
}

function RecordingsMock({ animate }: { animate: boolean }) {
  const rows = [
    { t: "SQL — Window functions", p: 100 },
    { t: "Spark — Partitioning", p: 64 },
    { t: "Airflow — DAG design", p: 22 },
  ];
  return (
    <div className="rounded-[14px] bg-white p-5">
      <div className="mb-3 flex items-center justify-between">
        <span className="mono text-[10px] uppercase tracking-widest text-muted">Recordings</span>
        <span className="flex items-center gap-1.5 text-[11px] font-semibold text-verify">
          <span className="h-1.5 w-1.5 rounded-full bg-verify" /> Live Thu 7:00
        </span>
      </div>
      <div className="space-y-3">
        {rows.map((r, i) => (
          <div key={r.t}>
            <div className="flex items-center justify-between">
              <span className="text-xs text-ink">{r.t}</span>
              <span className="mono text-[10px] text-muted">{r.p}%</span>
            </div>
            <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-line">
              <motion.div
                className="h-full bg-trust"
                initial={{ width: animate ? 0 : `${r.p}%` }}
                animate={{ width: `${r.p}%` }}
                transition={{ duration: durations.slower, ease, delay: i * 0.12 }}
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function VoiceMock({ animate }: { animate: boolean }) {
  const bars = [40, 70, 100, 55, 85, 45, 95, 60, 75, 50, 90, 40, 65, 80];
  return (
    <div className="rounded-[14px] bg-white p-5">
      <div className="flex h-10 items-center gap-1">
        {bars.map((h, i) => (
          <motion.span
            key={i}
            className="w-1 rounded-full bg-trust/70"
            style={{ height: `${h}%` }}
            animate={animate ? { scaleY: [0.4, 1, 0.5] } : undefined}
            transition={{ duration: 1, ease, delay: i * 0.04, repeat: animate ? Infinity : 0, repeatType: "mirror" }}
          />
        ))}
      </div>
      <div className="mt-4 rounded-lg border border-line px-3 py-2.5">
        <p className="mono text-[10px] uppercase tracking-widest text-muted">Gap vs real interviews</p>
        <p className="mt-1 text-xs text-ink">SQL optimisation is weighted 30% — you&rsquo;re at 55%.</p>
        <div className="mt-2.5 space-y-1.5">
          <Bar label="Real weight" pct={30} tone="ink" animate={animate} />
          <Bar label="You" pct={55} tone="trust" animate={animate} />
        </div>
      </div>
    </div>
  );
}

function Bar({ label, pct, tone, animate }: { label: string; pct: number; tone: "ink" | "trust"; animate: boolean }) {
  return (
    <div className="flex items-center gap-2">
      <span className="mono w-16 shrink-0 text-[9px] text-muted">{label}</span>
      <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-line">
        <motion.div
          className={`h-full ${tone === "trust" ? "bg-trust" : "bg-ink2"}`}
          initial={{ width: animate ? 0 : `${pct}%` }}
          animate={{ width: `${pct}%` }}
          transition={{ duration: durations.slower, ease }}
        />
      </div>
    </div>
  );
}

function CvMock({ animate }: { animate: boolean }) {
  const blocks = [88, 70, 94, 60, 80, 50];
  return (
    <div className="rounded-[14px] bg-white p-5">
      <div className="flex items-start justify-between">
        <div className="space-y-1.5">
          <div className="h-3 w-28 rounded bg-ink2/70" />
          <div className="h-2 w-20 rounded bg-line" />
        </div>
        <div className="text-center">
          <div className="display text-2xl text-trust">
            <AtsScore target={94} animate={animate} />
          </div>
          <p className="mono text-[9px] uppercase tracking-widest text-muted">ATS score</p>
        </div>
      </div>
      <div className="mt-4 space-y-2">
        {blocks.map((w, i) => (
          <motion.div
            key={i}
            className="h-2 rounded bg-line"
            style={{ width: `${w}%` }}
            initial={{ opacity: animate ? 0 : 1, x: animate ? -8 : 0 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: durations.slow, ease, delay: i * 0.09 }}
          />
        ))}
      </div>
    </div>
  );
}

function AtsScore({ target, animate }: { target: number; animate: boolean }) {
  const [n, setN] = useState(animate ? 0 : target);

  useEffect(() => {
    if (!animate) {
      setN(target);
      return;
    }
    let raf = 0;
    const t0 = performance.now();
    const step = (t: number) => {
      const k = Math.min(1, (t - t0) / 1000);
      setN(Math.round(target * (1 - Math.pow(1 - k, 3))));
      if (k < 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
    return () => cancelAnimationFrame(raf);
  }, [animate, target]);

  return <>{n}</>;
}

function DeployMock({ animate }: { animate: boolean }) {
  const jobs = [
    { t: "Data Engineer · Bengaluru", m: 94 },
    { t: "Analytics Engineer · Remote", m: 88 },
    { t: "ETL Developer · Pune", m: 81 },
  ];
  const confetti = [0, 1, 2, 3, 4, 5, 6, 7];
  return (
    <div className="rounded-[14px] bg-white p-5">
      <div className="space-y-2">
        {jobs.map((j, i) => (
          <motion.div
            key={j.t}
            className="flex items-center justify-between rounded-lg border border-line px-3 py-2"
            initial={{ opacity: animate ? 0 : 1, y: animate ? 8 : 0 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: durations.slow, ease, delay: i * 0.1 }}
          >
            <span className="text-xs text-ink">{j.t}</span>
            <span className="mono rounded-full bg-sky px-2 py-0.5 text-[10px] font-semibold text-deep">{j.m}% match</span>
          </motion.div>
        ))}
      </div>
      <div className="relative mt-4 flex items-center justify-between rounded-lg bg-verify-bg px-3 py-2.5">
        <span className="mono text-[10px] uppercase tracking-widest text-verify">Applied → Interview → Offer</span>
        <span className="text-xs font-semibold text-verify">Offer received</span>
        {animate &&
          confetti.map((c) => (
            <motion.span
              key={c}
              className="absolute left-1/2 top-1/2 h-1.5 w-1.5 rounded-full"
              style={{ backgroundColor: ["#1B6DF0", "#0BA860", "#F5A623"][c % 3] }}
              initial={{ opacity: 1, x: 0, y: 0 }}
              animate={{ opacity: 0, x: (c - 3.5) * 22, y: [-4, -26] }}
              transition={{ duration: 1.1, ease, delay: 0.2 }}
            />
          ))}
      </div>
    </div>
  );
}
