"use client";

import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";

/**
 * The hero's living engine visual: the reverse-engineering story as one calm loop —
 * transcript lines flow in (LISTEN), keyword tokens light up and lift (EXTRACT),
 * syllabus blocks assemble (REBUILD). A highlight travels left→right tying the three
 * together. transform/opacity only; under reduced motion it renders one composed,
 * fully-lit frame.
 */

const TOKENS = ["SQL", "Airflow", "dbt", "Spark", "CI/CD", "Kafka"];
const LINE_WIDTHS = [92, 74, 84, 60, 88, 70, 80, 54];

export function HeroEngine() {
  const reduce = useReducedMotion();
  const [phase, setPhase] = useState(0); // 0 LISTEN · 1 EXTRACT · 2 REBUILD

  useEffect(() => {
    if (reduce) return;
    const id = window.setInterval(() => setPhase((p) => (p + 1) % 3), 2000);
    return () => window.clearInterval(id);
  }, [reduce]);

  const on = (stage: number) => reduce || phase === stage;
  const lit = (stage: number) => (reduce || phase >= stage ? 1 : 0.4);

  return (
    <div className="relative mx-auto w-full max-w-[440px] select-none" aria-hidden>
      <div className="rounded-[22px] border border-line bg-white/70 p-5 shadow-soft backdrop-blur-sm">
        <div className="grid grid-cols-[1.1fr_1fr_1.2fr] gap-3">
          {/* LISTEN — transcript lines */}
          <Column label="LISTEN" active={on(0)}>
            <div className="space-y-1.5">
              {LINE_WIDTHS.map((w, i) => (
                <motion.div
                  key={i}
                  className="h-1.5 rounded-full bg-ink2/25"
                  style={{ width: `${w}%` }}
                  animate={reduce ? undefined : { opacity: phase === 0 ? [0.35, 1, 0.6] : 0.4 }}
                  transition={{ duration: 1.6, ease, delay: i * 0.08, repeat: phase === 0 ? Infinity : 0 }}
                />
              ))}
            </div>
          </Column>

          {/* EXTRACT — keyword tokens lighting up */}
          <Column label="EXTRACT" active={on(1)}>
            <div className="flex flex-wrap content-start gap-1.5">
              {TOKENS.map((t, i) => (
                <motion.span
                  key={t}
                  className="mono rounded-full border px-2 py-0.5 text-[10px]"
                  animate={{
                    opacity: lit(1),
                    y: reduce ? 0 : phase === 1 ? [-1, -3, -1] : 0,
                    borderColor: on(1) ? "rgba(27,109,240,0.6)" : "rgba(220,230,245,1)",
                    color: on(1) ? "var(--bj-trust)" : "var(--bj-muted)",
                    backgroundColor: on(1) ? "rgba(231,241,254,0.9)" : "rgba(255,255,255,0)",
                  }}
                  transition={{ duration: durations.slow, ease, delay: i * 0.06, repeat: phase === 1 ? Infinity : 0, repeatType: "mirror" }}
                >
                  {t}
                </motion.span>
              ))}
            </div>
          </Column>

          {/* REBUILD — syllabus blocks assembling */}
          <Column label="REBUILD" active={on(2)}>
            <div className="grid grid-cols-2 gap-1.5">
              {Array.from({ length: 6 }).map((_, i) => (
                <motion.div
                  key={i}
                  className="h-6 rounded-md"
                  animate={{
                    opacity: lit(2),
                    scale: reduce ? 1 : on(2) ? [0.9, 1] : 0.96,
                    backgroundColor: on(2) ? "rgba(27,109,240,0.14)" : "rgba(27,42,68,0.06)",
                  }}
                  transition={{ duration: durations.slow, ease, delay: i * 0.07 }}
                />
              ))}
            </div>
          </Column>
        </div>

        {/* Traveling connector — the flow L→R */}
        <div className="relative mt-4 h-px bg-line">
          {!reduce && (
            <motion.div
              className="absolute top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-trust shadow-[0_0_10px_rgba(27,109,240,0.7)]"
              animate={{ left: ["6%", "50%", "88%"] }}
              transition={{ duration: 6, ease, repeat: Infinity, times: [0, 0.5, 1] }}
            />
          )}
        </div>
      </div>
    </div>
  );
}

function Column({ label, active, children }: { label: string; active: boolean; children: React.ReactNode }) {
  return (
    <div>
      <div className="mb-2 flex items-center gap-1.5">
        <span className={`h-1.5 w-1.5 rounded-full ${active ? "bg-trust" : "bg-line"}`} />
        <span className={`mono text-[9px] tracking-[0.12em] ${active ? "text-ink" : "text-muted"}`}>{label}</span>
      </div>
      {children}
    </div>
  );
}
