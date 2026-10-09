"use client";

import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";

/**
 * A diagram of the two paths. Bar length is a picture, not a measured chart.
 * The labels carry the only figures: 90 days and 3 days.
 */
export function DaysCompare() {
  const reduce = useReducedMotion();

  return (
    <div className="rounded-[22px] border border-line bg-white p-5 shadow-soft md:p-7">
      <p className="mono text-[11px] uppercase tracking-[0.16em] text-muted">Two paths</p>
      <div className="mt-5 space-y-6">
        <Lane
          label="The old way"
          figure="90 days"
          detail="Manual calls. Surprises."
          scale={1}
          tone="muted"
          reduce={!!reduce}
        />
        <Lane
          label="BrowseJobs"
          figure="3 days"
          detail="WhatsApp bots. A report before you meet."
          scale={0.34}
          tone="blue"
          reduce={!!reduce}
        />
      </div>
      <p className="mono mt-5 text-[11px] leading-relaxed text-muted">
        A picture of the two paths. Not a chart of your hiring.
      </p>
    </div>
  );
}

function Lane({
  label,
  figure,
  detail,
  scale,
  tone,
  reduce,
}: {
  label: string;
  figure: string;
  detail: string;
  scale: number;
  tone: "muted" | "blue";
  reduce: boolean;
}) {
  const fill = tone === "blue" ? "bg-trust" : "bg-ink/20";
  return (
    <div>
      <div className="flex items-baseline justify-between gap-4">
        <p className="text-sm font-semibold text-ink">{label}</p>
        <p className={`mono text-2xl ${tone === "blue" ? "text-trust" : "text-ink2"}`}>{figure}</p>
      </div>
      <motion.div
        className="mt-3 h-2 overflow-hidden rounded-full bg-paper"
        initial="hidden"
        whileInView="show"
        viewport={{ once: true, margin: "-40px" }}
      >
        <motion.div
          className={`h-full origin-left rounded-full ${fill}`}
          style={{ width: `${scale * 100}%` }}
          variants={{
            hidden: { scaleX: reduce ? 1 : 0 },
            show: {
              scaleX: 1,
              transition: { duration: reduce ? 0 : durations.slower, ease },
            },
          }}
        />
      </motion.div>
      <p className="mt-2 text-sm text-muted">{detail}</p>
    </div>
  );
}
