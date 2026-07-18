"use client";

import { useRef, useState } from "react";
import { motion, useReducedMotion, useScroll, useMotionValueEvent } from "framer-motion";
import { durations, ease } from "@/lib/motion";

/**
 * S2 — the Proof Engine, scrubbed by scroll. As the panel moves through the viewport the
 * three stages light in sequence: LISTEN (waveform) → EXTRACT (tokens cluster) → REBUILD
 * (modules snap in), with a rail that fills to the active stage. No pin, no scroll-jack —
 * scroll stays the user's. Under reduced motion all three are lit and still.
 */

const STAGES = [
  { key: "LISTEN", desc: "~50 real & mock interviews monitored daily" },
  { key: "EXTRACT", desc: "the questions companies ask this month" },
  { key: "REBUILD", desc: "the syllabus, rebuilt around live demand" },
] as const;

export function ProofSequence() {
  const reduce = useReducedMotion();
  const ref = useRef<HTMLDivElement>(null);
  const [stage, setStage] = useState(reduce ? 2 : 0);

  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start 0.75", "end 0.55"],
  });

  useMotionValueEvent(scrollYProgress, "change", (p) => {
    if (reduce) return;
    setStage(p < 0.34 ? 0 : p < 0.67 ? 1 : 2);
  });

  const lit = (i: number) => reduce || stage >= i;

  return (
    <div ref={ref}>
      <div className="grid gap-3 sm:grid-cols-3">
        {STAGES.map((s, i) => {
          const active = reduce || stage === i;
          return (
            <motion.div
              key={s.key}
              animate={{ opacity: lit(i) ? 1 : 0.4, y: active && !reduce ? -2 : 0 }}
              transition={{ duration: durations.slow, ease }}
              className={`rounded-[14px] border p-5 ${active ? "border-trust/60 bg-white/5" : "border-white/10"}`}
            >
              <div className="flex items-center gap-2.5">
                <span className={`h-2 w-2 rounded-full ${lit(i) ? "bg-trust" : "bg-white/25"}`} />
                <span className="mono text-sm tracking-[0.18em] text-white">{s.key}</span>
              </div>

              <div className="mt-4 h-12">
                {i === 0 && <Waveform active={active} reduce={reduce} />}
                {i === 1 && <Tokens active={lit(1)} reduce={reduce} />}
                {i === 2 && <Modules active={lit(2)} reduce={reduce} />}
              </div>

              <p className="mt-3 text-sm leading-relaxed text-sky/70">{s.desc}</p>
            </motion.div>
          );
        })}
      </div>

      {/* Progress rail — fills to the active stage. */}
      <div className="mt-5 h-0.5 overflow-hidden rounded-full bg-white/10">
        <motion.div
          className="h-full bg-trust/70"
          animate={{ width: reduce ? "100%" : `${((stage + 1) / 3) * 100}%` }}
          transition={{ duration: durations.slow, ease }}
        />
      </div>
    </div>
  );
}

function Waveform({ active, reduce }: { active: boolean; reduce: boolean | null }) {
  const bars = [40, 70, 100, 60, 85, 45, 95, 55, 75, 35, 90, 50];
  return (
    <div className="flex h-full items-center gap-1">
      {bars.map((h, i) => (
        <motion.span
          key={i}
          className="w-1 rounded-full bg-trust/70"
          style={{ height: `${h}%` }}
          animate={reduce ? { opacity: 0.8 } : { scaleY: active ? [0.5, 1, 0.6] : 0.4, opacity: active ? 1 : 0.4 }}
          transition={{ duration: 1.1, ease, delay: i * 0.05, repeat: active && !reduce ? Infinity : 0, repeatType: "mirror" }}
        />
      ))}
    </div>
  );
}

function Tokens({ active, reduce }: { active: boolean; reduce: boolean | null }) {
  const tokens = ["SQL", "Airflow", "Kafka", "dbt", "Spark"];
  return (
    <div className="flex flex-wrap content-start gap-1.5">
      {tokens.map((t, i) => (
        <motion.span
          key={t}
          className="mono rounded-full border border-trust/40 bg-trust/10 px-2 py-0.5 text-[10px] text-sky"
          animate={{ opacity: active ? 1 : 0.35, y: reduce ? 0 : active ? [2, 0] : 4 }}
          transition={{ duration: durations.slow, ease, delay: i * 0.06 }}
        >
          {t}
        </motion.span>
      ))}
    </div>
  );
}

function Modules({ active, reduce }: { active: boolean; reduce: boolean | null }) {
  return (
    <div className="grid h-full grid-cols-4 gap-1.5">
      {Array.from({ length: 8 }).map((_, i) => (
        <motion.span
          key={i}
          className="rounded-md bg-trust/25"
          animate={{ opacity: active ? 1 : 0.25, scale: reduce ? 1 : active ? [0.85, 1] : 0.9 }}
          transition={{ duration: durations.slow, ease, delay: i * 0.05 }}
        />
      ))}
    </div>
  );
}
