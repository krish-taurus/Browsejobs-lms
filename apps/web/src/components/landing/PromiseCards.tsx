"use client";

import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { Kicker } from "@/components/brand/Kicker";
import { promisesKept, promisesNever } from "@/content/landing";

function Check() {
  return (
    <svg viewBox="0 0 20 20" className="mt-0.5 h-5 w-5 shrink-0" fill="none">
      <circle cx="10" cy="10" r="10" fill="var(--bj-verify)" opacity="0.15" />
      <path d="M6 10.5l2.5 2.5L14 7.5" stroke="var(--bj-verify)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function Cross() {
  return (
    <svg viewBox="0 0 20 20" className="mt-0.5 h-5 w-5 shrink-0" fill="none">
      <circle cx="10" cy="10" r="10" fill="var(--bj-warn)" opacity="0.15" />
      <path d="M7 7l6 6M13 7l-6 6" stroke="var(--bj-warn)" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

/**
 * S7 — the green/red promise pair, the trust device that converts skeptics. The two
 * columns slide in from opposite sides so they read as a deliberate face-off. Static
 * under reduced motion.
 */
export function PromiseCards() {
  const reduce = useReducedMotion();
  const from = (x: number) =>
    reduce
      ? {}
      : {
          initial: { opacity: 0, x },
          whileInView: { opacity: 1, x: 0 },
          viewport: { once: true, margin: "-80px" },
          transition: { duration: durations.slower, ease },
        };

  return (
    <section className="mx-auto max-w-6xl px-5 py-20 md:py-28">
      <div className="mb-12 text-center">
        <Kicker>In writing</Kicker>
        <h2 className="display mx-auto mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
          What we promise — and what we never will
        </h2>
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        <motion.div {...from(-40)} className="h-full rounded-[22px] border border-verify/30 bg-verify-bg p-8 md:p-10">
          <Kicker tone="verify">What we promise in writing</Kicker>
          <ul className="mt-6 space-y-4">
            {promisesKept.map((p) => (
              <li key={p} className="flex gap-3 text-ink">
                <Check />
                <span>{p}</span>
              </li>
            ))}
          </ul>
        </motion.div>

        <motion.div {...from(40)} className="h-full rounded-[22px] border border-warn/30 bg-warn/5 p-8 md:p-10">
          <p className="kicker text-warn">What we will never tell you</p>
          <ul className="mt-6 space-y-4">
            {promisesNever.map((p) => (
              <li key={p} className="flex gap-3 text-ink">
                <Cross />
                <span>{p}</span>
              </li>
            ))}
          </ul>
        </motion.div>
      </div>
    </section>
  );
}
