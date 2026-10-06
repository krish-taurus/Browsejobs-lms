"use client";

import { useEffect, useState } from "react";
import { LayoutGroup, motion, useReducedMotion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";

const PHASES = ["Interview", "Score", "HR queue"] as const;
type Phase = 0 | 1 | 2;

const HOLD_MS = durations.slower * 3200;

const BARS = [
  { label: "Technical depth", amount: 0.86 },
  { label: "Communication", amount: 0.74 },
  { label: "Evidence of experience", amount: 0.9 },
] as const;

/**
 * Hero product sequence: interview → rubric fills → pass stamp → the card
 * leaps into the HR priority queue. Transform and opacity only.
 * Reduced motion renders the finished frame, with no loop.
 */
export function ReverseHireDemo() {
  return (
    <div className="relative">
      <div className="hidden motion-safe:block">
        <Stage live />
      </div>
      <div className="hidden motion-reduce:block">
        <Stage live={false} />
      </div>
    </div>
  );
}

function Stage({ live }: { live: boolean }) {
  const reduce = useReducedMotion();
  const [phase, setPhase] = useState<Phase>(live ? 0 : 2);
  const [paused, setPaused] = useState(false);
  const cardId = live ? "reverse-hire-card-live" : "reverse-hire-card-still";

  useEffect(() => {
    if (!live || reduce || paused) return;
    const id = window.setInterval(() => {
      setPhase((current) => ((current + 1) % 3) as Phase);
    }, HOLD_MS);
    return () => window.clearInterval(id);
  }, [live, reduce, paused]);

  const scored = phase >= 1;
  const queued = phase >= 2;

  return (
    <div
      className="rounded-panel bg-ink p-4 text-white shadow-soft md:p-5"
      onMouseEnter={() => live && setPaused(true)}
      onMouseLeave={() => live && setPaused(false)}
    >
      <div className="mb-3 flex items-center justify-between gap-3">
        <p className="kicker text-sky/80">Sample screen</p>
        <div className="flex gap-1.5" role="tablist" aria-label="Interview sequence">
          {PHASES.map((label, index) => (
            <button
              key={label}
              type="button"
              role="tab"
              aria-selected={phase === index}
              onClick={() => {
                setPaused(true);
                setPhase(index as Phase);
              }}
              className={`mono rounded-full px-2.5 py-1 text-[10px] uppercase tracking-widest ${
                phase === index ? "bg-white text-ink" : "bg-white/10 text-white/60"
              }`}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      <LayoutGroup>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="rounded-card bg-white/5 p-4">
            <div className="flex items-center justify-between">
              <p className="text-xs font-semibold">AI interview</p>
              <span className="mono flex items-center gap-1.5 text-[10px] text-white/50">
                <motion.span
                  className="h-1.5 w-1.5 rounded-full bg-trust"
                  animate={live && phase === 0 ? { opacity: [1, 0.35, 1] } : { opacity: 0.8 }}
                  transition={
                    live && phase === 0
                      ? { duration: durations.slower, repeat: Infinity, ease }
                      : { duration: durations.fast, ease }
                  }
                />
                {phase === 0 ? "Live" : "Graded"}
              </span>
            </div>
            <p className="mt-3 text-[13px] italic leading-snug text-white/75">
              “Walk me through how you would find a duplicate row without guessing.”
            </p>
            <div className="mt-4 flex h-8 items-end gap-[3px]" aria-hidden>
              {Array.from({ length: 18 }).map((_, i) => (
                <motion.span
                  key={i}
                  className="w-[3px] origin-bottom rounded-full bg-trust"
                  style={{ height: "100%" }}
                  initial={false}
                  animate={{
                    scaleY: live && phase === 0 ? [0.25, 0.35 + ((i * 17) % 10) / 14, 0.25] : scored ? 0.2 : 0.35,
                  }}
                      transition={
                    live && phase === 0
                      ? { duration: durations.slower, repeat: Infinity, ease, delay: (i % 6) * stagger }
                      : { duration: durations.base, ease }
                  }
                />
              ))}
            </div>
            <ul className="mt-4 space-y-2.5">
              {BARS.map((bar, i) => (
                <li key={bar.label}>
                  <div className="mb-1 flex justify-between text-[10px] text-white/50">
                    <span>{bar.label}</span>
                    <span className="mono">{scored ? "Sample" : "—"}</span>
                  </div>
                  <div className="h-1 overflow-hidden rounded-full bg-white/10">
                    <motion.div
                      className="h-full origin-left rounded-full bg-trust"
                      initial={false}
                      animate={{ scaleX: scored ? bar.amount : 0.08 }}
                      transition={{ duration: durations.slower, ease, delay: scored ? i * 0.07 : 0 }}
                    />
                  </div>
                </li>
              ))}
            </ul>
            <div className="mt-4 min-h-[4.5rem] space-y-2">
              {!queued && <CandidateCard layoutId={cardId} />}
              {scored && (
                <motion.span
                  initial={live ? { opacity: 0, scale: 0.86 } : false}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ duration: durations.slow, ease }}
                  className="mono inline-flex rounded-full bg-verify px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-white"
                >
                  Cleared · sample
                </motion.span>
              )}
            </div>
          </div>

          <div className="rounded-card border border-white/10 bg-white/[0.03] p-4">
            <p className="kicker text-sky/70">In front of HR</p>
            <p className="mt-2 text-[11px] leading-snug text-white/45">Ranked on skills and the mock. Sample rows — not people.</p>
            <ul className="mt-4 space-y-2">
              <li className="min-h-[52px]">
                {queued ? (
                  <CandidateCard layoutId={cardId} priority />
                ) : (
                  <QueueRow label="Waiting on a grade" muted />
                )}
              </li>
              <li>
                <QueueRow label="Not yet interviewed" muted />
              </li>
              <li>
                <QueueRow label="Inbox — not a ranking" muted />
              </li>
            </ul>
          </div>
        </div>
      </LayoutGroup>
      <p className="mono mt-3 text-[10px] leading-relaxed text-white/40">
        Sample sequence. A real score stays empty until your screen is graded.
        {live ? " Tap a step to hold the frame." : ""}
      </p>
    </div>
  );
}

function CandidateCard({ layoutId, priority = false }: { layoutId: string; priority?: boolean }) {
  return (
    <motion.div
      layoutId={layoutId}
      transition={{ layout: { duration: durations.slower, ease } }}
      className={`flex items-center justify-between rounded-card px-3 py-2 ${
        priority ? "bg-white text-ink" : "bg-white/10 text-white"
      }`}
    >
      <div>
        <p className="text-xs font-semibold">You</p>
        <p className={`text-[10px] ${priority ? "text-muted" : "text-white/50"}`}>Skills + mock</p>
      </div>
      {priority && (
        <span className="mono rounded-full bg-verify-bg px-2 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-verify">
          Priority
        </span>
      )}
    </motion.div>
  );
}

function QueueRow({ label, muted }: { label: string; muted?: boolean }) {
  return (
    <div className={`rounded-card px-3 py-2 text-xs ${muted ? "bg-white/5 text-white/35" : "bg-white/10"}`}>{label}</div>
  );
}
