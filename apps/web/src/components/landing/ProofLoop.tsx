"use client";

import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";

const STEPS = [
  { key: "LISTEN", desc: "~50 real & mock interviews monitored daily" },
  { key: "EXTRACT", desc: "the questions companies ask this month" },
  { key: "REBUILD", desc: "the syllabus, rebuilt around live demand" },
] as const;

/**
 * The Proof Engine loop: LISTEN → EXTRACT → REBUILD, cycling highlight with a
 * progress sweep between nodes. Static under prefers-reduced-motion.
 */
export function ProofLoop() {
  const reduce = useReducedMotion();
  const [active, setActive] = useState(0);

  useEffect(() => {
    if (reduce) return;
    const id = window.setInterval(
      () => setActive((a) => (a + 1) % STEPS.length),
      2200,
    );
    return () => window.clearInterval(id);
  }, [reduce]);

  return (
    <div>
      <div className="grid gap-3 sm:grid-cols-3">
        {STEPS.map((step, i) => {
          const isActive = reduce || i === active;
          return (
            <motion.div
              key={step.key}
              animate={{
                opacity: isActive ? 1 : 0.45,
                scale: !reduce && i === active ? 1.02 : 1,
              }}
              transition={{ duration: durations.slow, ease }}
              className={`rounded-[14px] border p-5 ${
                isActive ? "border-trust/60 bg-white/5" : "border-white/10"
              }`}
            >
              <div className="flex items-center gap-2.5">
                <span
                  className={`h-2 w-2 rounded-full ${
                    isActive ? "bg-trust" : "bg-white/25"
                  }`}
                />
                <span className="mono text-sm tracking-[0.18em] text-white">
                  {step.key}
                </span>
              </div>
              <p className="mt-2.5 text-sm leading-relaxed text-sky/70">
                {step.desc}
              </p>
            </motion.div>
          );
        })}
      </div>

      {/* Progress sweep under the row */}
      {!reduce && (
        <div className="mt-4 h-0.5 overflow-hidden rounded-full bg-white/10">
          <motion.div
            key={active}
            initial={{ x: "-100%" }}
            animate={{ x: "0%" }}
            transition={{ duration: 2.2, ease: "linear" }}
            className="h-full w-full bg-trust/70"
          />
        </div>
      )}
    </div>
  );
}
