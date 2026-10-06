"use client";

import { motion, useReducedMotion } from "framer-motion";
import { HIRING_JOURNEY } from "@/content/employer-landing";
import { durations, ease, stagger } from "@/lib/motion";
import { DaysCompare } from "@/components/employers/DaysCompare";

export function HiringJourney() {
  const reduce = useReducedMotion();

  return (
    <div className="mt-10 grid items-start gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
      <div className="order-1 lg:order-2">
        <DaysCompare />
      </div>
      <motion.ol
        className="relative order-2 space-y-0 lg:order-1"
        initial="hidden"
        whileInView="show"
        viewport={{ once: true, margin: "-80px" }}
      >
        <motion.span
          aria-hidden
          className="absolute bottom-3 left-[7px] top-3 w-px origin-top bg-trust"
          variants={{
            hidden: { scaleY: reduce ? 1 : 0 },
            show: { scaleY: 1, transition: { duration: reduce ? 0 : durations.slower, ease } },
          }}
        />
        {HIRING_JOURNEY.map((step, index) => (
          <motion.li
            key={step.kicker}
            className="relative py-3 pl-8"
            initial={reduce ? false : { opacity: 0, y: 18 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: "-40px" }}
            transition={{
              duration: reduce ? 0 : durations.base,
              ease,
              delay: reduce ? 0 : Math.min(index, 4) * stagger,
            }}
          >
            <span
              aria-hidden
              className="absolute left-0 top-5 h-4 w-4 rounded-full border-2 border-trust bg-white"
            />
            <p className="mono text-[11px] font-semibold uppercase tracking-[0.14em] text-trust">{step.kicker}</p>
            <h3 className="mt-1 text-lg font-semibold text-ink">{step.title}</h3>
            <p className="mt-1 text-sm leading-relaxed text-ink2">{step.body}</p>
          </motion.li>
        ))}
      </motion.ol>
    </div>
  );
}
