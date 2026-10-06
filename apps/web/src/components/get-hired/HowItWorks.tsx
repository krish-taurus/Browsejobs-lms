"use client";

import { useRef, useState } from "react";
import { AnimatePresence, motion, useMotionValueEvent, useReducedMotion, useScroll, useTransform } from "framer-motion";
import { REVERSE_STEPS } from "@/content/get-hired";
import { durations, ease, stagger } from "@/lib/motion";

/**
 * Desktop: scroll drives which step is loud and which product frame is on the
 * right. Mobile and reduced motion: the same four steps, stacked, still complete.
 */
export function HowItWorks() {
  return (
    <section id="how" className="border-y border-line bg-paper">
      <div className="md:motion-safe:hidden">
        <StepStack />
      </div>
      <div className="hidden md:motion-safe:block">
        <StepScrub />
      </div>
    </section>
  );
}

function StepStack() {
  return (
    <div className="mx-auto max-w-6xl px-6 py-16 md:py-24">
      <Header />
      <ol className="mt-10 space-y-4">
        {REVERSE_STEPS.map((step, index) => (
          <li key={step.n} className="overflow-hidden rounded-panel border border-line bg-white">
            <div className="grid gap-0 md:grid-cols-2">
              <div className="p-6 md:p-8">
                <p className="mono text-xs text-trust">{step.n}</p>
                <h3 className="display mt-2 text-2xl">{step.title}</h3>
                <p className="mt-3 text-[15px] leading-relaxed text-muted">{step.body}</p>
              </div>
              <div className="border-t border-line bg-ink p-6 text-white md:border-l md:border-t-0">
                <StepFrame index={index} />
              </div>
            </div>
          </li>
        ))}
      </ol>
    </div>
  );
}

function StepScrub() {
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ["start start", "end end"] });
  const [active, setActive] = useState(0);
  const bar = useTransform(scrollYProgress, [0, 1], [0, 1]);

  useMotionValueEvent(scrollYProgress, "change", (value) => {
    const next = Math.min(REVERSE_STEPS.length - 1, Math.floor(value * REVERSE_STEPS.length));
    setActive(next);
  });

  return (
    <div ref={ref} className="relative h-[240vh]">
      <div className="sticky top-16 flex h-[calc(100vh-4rem)] items-center">
        <div className="mx-auto grid w-full max-w-6xl grid-cols-[0.9fr_1.1fr] items-center gap-12 px-6">
          <div>
            <Header />
            <ol className="mt-8 space-y-2">
              {REVERSE_STEPS.map((step, index) => {
                const on = index === active;
                return (
                  <li key={step.n}>
                    <motion.div
                      animate={{ opacity: on ? 1 : 0.38 }}
                      transition={{ duration: durations.base, ease }}
                      className={`rounded-card px-3 py-3 ${on ? "border-l-2 border-trust bg-white" : ""}`}
                    >
                      <p className="mono text-[11px] text-trust">
                        {step.n} · {step.kicker}
                      </p>
                      <h3 className="display mt-1 text-xl">{step.title}</h3>
                      <p className={`mt-1 text-sm leading-relaxed text-muted ${on ? "" : "sr-only"}`}>{step.body}</p>
                    </motion.div>
                  </li>
                );
              })}
            </ol>
            <div className="mt-6 h-1 overflow-hidden rounded-full bg-line">
              <motion.div style={{ scaleX: bar }} className="h-full w-full origin-left rounded-full bg-trust" />
            </div>
          </div>
          <div className="overflow-hidden rounded-panel bg-ink p-8 text-white shadow-soft">
            <AnimatePresence mode="wait">
              <motion.div
                key={active}
                initial={{ opacity: 0, y: 18 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -12 }}
                transition={{ duration: durations.base, ease }}
              >
                <StepFrame index={active} />
              </motion.div>
            </AnimatePresence>
          </div>
        </div>
      </div>
    </div>
  );
}

function Header() {
  return (
    <div>
      <p className="kicker text-trust">How it works</p>
      <h2 className="display mt-3 text-3xl leading-[1.05] md:text-5xl">Three steps. Then you know.</h2>
      <p className="mt-3 max-w-md text-muted">You sit the interview. You see the score. Then HR sees you, or counselling shows the block.</p>
    </div>
  );
}

function StepFrame({ index }: { index: number }) {
  const reduce = useReducedMotion();
  if (index === 0) {
    return (
      <div>
        <p className="kicker text-sky/70">01 · AI interview</p>
        <p className="mt-4 text-lg italic leading-snug text-white/80">
          “Tell me about a pipeline you owned. What broke, and what did you change?”
        </p>
        <div className="mt-6 flex h-10 items-end gap-1" aria-hidden>
          {Array.from({ length: 22 }).map((_, i) => (
            <motion.span
              key={i}
              className="w-1 origin-bottom rounded-full bg-trust"
              style={{ height: "100%" }}
              initial={{ scaleY: 0.2 }}
              animate={reduce ? { scaleY: 0.45 } : { scaleY: [0.25, 0.4 + ((i * 13) % 8) / 12, 0.25] }}
              transition={
                reduce
                  ? { duration: durations.fast }
                  : { duration: durations.slower, repeat: Infinity, ease, delay: (i % 5) * stagger }
              }
            />
          ))}
        </div>
        <p className="mono mt-6 text-[11px] text-white/40">You book it. Not a self-serve button.</p>
      </div>
    );
  }

  if (index === 1) {
    return (
      <div>
        <p className="kicker text-sky/70">02 · Rubric</p>
        <ul className="mt-5 space-y-4">
          {[
            ["Technical depth", 0.82],
            ["Communication", 0.7],
            ["Evidence of experience", 0.88],
          ].map(([label, amount], i) => (
            <li key={String(label)}>
              <div className="mb-1 flex justify-between text-xs text-white/55">
                <span>{label}</span>
                <span className="mono">Sample</span>
              </div>
              <div className="h-1.5 overflow-hidden rounded-full bg-white/10">
                <motion.div
                  className="h-full origin-left rounded-full bg-trust"
                  initial={reduce ? false : { scaleX: 0 }}
                  animate={{ scaleX: amount as number }}
                  transition={{ duration: reduce ? 0 : durations.slower, ease, delay: reduce ? 0 : i * stagger }}
                />
              </div>
            </li>
          ))}
        </ul>
        <p className="mono mt-6 text-[11px] text-white/40">Empty until graded. This fill is a picture of the screen.</p>
      </div>
    );
  }

  return (
    <div>
      <p className="kicker text-sky/70">03 · Two ways</p>
      <div className="mt-5 grid gap-3">
        <motion.div
          initial={reduce ? false : { opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: reduce ? 0 : durations.slow, ease }}
          className="rounded-card border border-verify/40 bg-verify/10 p-4"
        >
          <p className="text-xs font-semibold text-verify">Clear</p>
          <p className="mt-1 text-sm text-white/80">We put you in front of HR with your score. Not a job offer.</p>
        </motion.div>
        <motion.div
          initial={reduce ? false : { opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: reduce ? 0 : durations.base, ease, delay: reduce ? 0 : stagger * 2 }}
          className="rounded-card border border-white/15 p-4"
        >
          <p className="text-xs font-semibold">Not clear</p>
          <p className="mt-1 text-sm text-white/70">
            Free counselling shows what&apos;s blocking you. A course only if you need it.
          </p>
        </motion.div>
      </div>
    </div>
  );
}
