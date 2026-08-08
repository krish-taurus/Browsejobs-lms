"use client";

import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { InkLabel, InkPanel, LiveDot } from "@/components/employers/ui";

/**
 * The hero's product frame: a pipeline that actually moves.
 *
 * Candidates rotate through the panel on a timer so the frame reads as a
 * queue being worked rather than a screenshot. Under reduced motion the
 * rotation never starts and the first three rows simply stand still — the
 * panel still says everything it needs to.
 */

const STAGES = [
  { label: "Applied", count: 261, width: "w-full" },
  { label: "Graded", count: 261, width: "w-full" },
  { label: "Shortlisted", count: 34, width: "w-1/2" },
  { label: "L1", count: 18, width: "w-1/3" },
  { label: "Offer", count: 4, width: "w-[8%]" },
];

const QUEUE = [
  { name: "Ananya R.", skills: "SQL · Python · Airflow", score: 78 },
  { name: "Rahul M.", skills: "Spark · dbt · Kafka", score: 91 },
  { name: "Sneha K.", skills: "PostgreSQL · Docker", score: 64 },
  { name: "Imran S.", skills: "SQL · Python · AWS", score: 71 },
  { name: "Meera V.", skills: "dbt · Snowflake · SQL", score: 83 },
  { name: "Arjun T.", skills: "Airflow · Spark · Scala", score: 69 },
];

const VISIBLE = 3;

function scoreTone(score: number): string {
  return score >= 80 ? "text-verify" : score >= 70 ? "text-trust" : "text-white/70";
}

export function LivePipeline() {
  const reduce = useReducedMotion();
  const [head, setHead] = useState(0);

  useEffect(() => {
    if (reduce) return;
    const t = window.setInterval(() => setHead((i) => (i + 1) % QUEUE.length), 2600);
    return () => window.clearInterval(t);
  }, [reduce]);

  const rows = Array.from({ length: VISIBLE }, (_, i) => QUEUE[(head + i) % QUEUE.length]);

  return (
    <InkPanel accent="employer" live className="p-5 md:p-6">
      <div className="flex items-center justify-between">
        <InkLabel>Pipeline · Data Engineer</InkLabel>
        <span className="mono inline-flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] text-white/40">
          <LiveDot />
          live
        </span>
      </div>

      <ul className="mt-5 space-y-3">
        {STAGES.map((s, i) => (
          <li key={s.label} className="flex items-center gap-3">
            <span className="w-24 shrink-0 text-[12px] text-white/55">{s.label}</span>
            <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-white/[0.07]">
              {/* Animates on mount, not on scroll: the panel sits above the
                  fold, so there is no scroll event to wait for and a
                  whileInView bar would simply never leave scaleX(0). */}
              <motion.span
                className={`block h-full rounded-full bg-gradient-to-r from-trust to-employer ${s.width}`}
                style={{ transformOrigin: "left" }}
                initial={reduce ? false : { scaleX: 0 }}
                animate={{ scaleX: 1 }}
                transition={{ duration: 0.9, ease, delay: 0.1 + i * 0.08 }}
              />
            </span>
            <span className="mono w-9 shrink-0 text-right text-[12px] text-white/70">
              <MonoCounter value={s.count} />
            </span>
          </li>
        ))}
      </ul>

      {/* The queue itself — rows enter from the bottom and leave at the top. */}
      <div className="mt-6 border-t border-white/[0.07] pt-5">
        <div className="relative h-[186px] overflow-hidden">
          <AnimatePresence initial={false} mode="popLayout">
            {rows.map((r, i) => (
              <motion.div
                key={`${r.name}-${head}-${i}`}
                layout
                initial={reduce ? false : { opacity: 0, y: 22 }}
                animate={{ opacity: 1, y: 0 }}
                exit={reduce ? undefined : { opacity: 0, y: -14 }}
                transition={{ duration: durations.slow, ease, delay: i * 0.05 }}
                className="mb-2 flex items-center justify-between rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3"
              >
                <div className="min-w-0">
                  <p className="truncate text-[13px] font-semibold text-white">{r.name}</p>
                  <p className="mono truncate text-[10px] uppercase tracking-[0.1em] text-white/35">
                    {r.skills}
                  </p>
                </div>
                <span className={`mono shrink-0 text-lg font-semibold ${scoreTone(r.score)}`}>
                  {r.score}
                </span>
              </motion.div>
            ))}
          </AnimatePresence>
          {/* Bottom fade, so a row leaving the frame dissolves rather than clips. */}
          <span
            aria-hidden
            className="pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t from-deck-card to-transparent"
          />
        </div>
      </div>

      <p className="mt-4 text-[11px] leading-relaxed text-white/35">
        Illustration of the workspace. Names and scores are fictional.
      </p>
    </InkPanel>
  );
}
