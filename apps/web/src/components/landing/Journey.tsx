"use client";

import { useRef, useState } from "react";
import { motion, useReducedMotion, useScroll, useTransform, useMotionValueEvent } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Kicker } from "@/components/brand/Kicker";

/**
 * S3 — Your Journey. A vertical line draws itself as you scroll (its fill height is tied
 * to scroll progress), lighting each node as it passes. The first three carry green FREE
 * chips. Under reduced motion the line is fully drawn and every node lit.
 */

const NODES = [
  { step: "01", free: true, title: "Free counselling", body: "A written Career Analysis Report — whether you join or not." },
  { step: "02", free: true, title: "Free live masterclass", body: "See the method live: how the syllabus is reverse-engineered." },
  { step: "03", free: true, title: "Free 7-hour bootcamp", body: "Sit a real teaching day of live Python before you decide." },
  { step: "04", free: false, title: "Paid batch", body: "Live, instructor-led. ₹30,000 — EMI 3 × ₹10,000." },
  { step: "05", free: false, title: "AI coach", body: "PRI, mastery map, and one clear next best action, daily." },
  { step: "06", free: false, title: "Mock interviews", body: "Voice mocks scored on real interview data, with a gap report." },
  { step: "07", free: false, title: "Placement", body: "Your profile worked until you accept an offer." },
];

export function Journey() {
  const reduce = useReducedMotion();
  const ref = useRef<HTMLDivElement>(null);
  const [reached, setReached] = useState(reduce ? NODES.length : 0);

  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start 0.7", "end 0.6"],
  });

  const fillHeight = useTransform(scrollYProgress, [0, 1], ["0%", "100%"]);

  useMotionValueEvent(scrollYProgress, "change", (p) => {
    if (reduce) return;
    setReached(Math.min(NODES.length, Math.floor(p * NODES.length) + 1));
  });

  return (
    <section id="free-steps" className="mx-auto max-w-3xl px-5 py-16 md:py-24">
      <ScrollReveal className="mb-12 text-center">
        <Kicker>The path</Kicker>
        <h2 className="display mt-3 text-3xl text-ink md:text-5xl">
          Three free steps before you pay a rupee
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-ink2/70">
          Scroll it. This is the whole journey, from your first free call to an accepted offer.
        </p>
      </ScrollReveal>

      <div ref={ref} className="relative pl-14">
        {/* Rail */}
        <div className="absolute left-[19px] top-2 bottom-2 w-0.5 bg-line" aria-hidden />
        <motion.div
          className="absolute left-[19px] top-2 w-0.5 origin-top bg-trust"
          style={{ height: reduce ? "100%" : fillHeight }}
          aria-hidden
        />

        <div className="space-y-10">
          {NODES.map((n, i) => {
            const lit = reduce || i < reached;
            return (
              <div key={n.step} className="relative">
                {/* Node dot */}
                <motion.span
                  className="absolute -left-[40px] top-0.5 grid h-6 w-6 place-items-center rounded-full border-2 bg-paper"
                  animate={{
                    borderColor: lit ? (n.free ? "var(--bj-verify)" : "var(--bj-trust)") : "var(--bj-line)",
                    scale: lit && !reduce ? 1 : 0.9,
                  }}
                  transition={{ duration: durations.slow, ease }}
                >
                  <motion.span
                    className="h-2 w-2 rounded-full"
                    animate={{ backgroundColor: lit ? (n.free ? "var(--bj-verify)" : "var(--bj-trust)") : "var(--bj-line)" }}
                    transition={{ duration: durations.slow, ease }}
                  />
                </motion.span>

                <motion.div animate={{ opacity: lit ? 1 : 0.45 }} transition={{ duration: durations.slow, ease }}>
                  <div className="flex items-center gap-2.5">
                    <span className="mono text-xs text-muted">STEP {n.step}</span>
                    {n.free && (
                      <span className="mono rounded-full bg-verify-bg px-2 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-verify">
                        Free
                      </span>
                    )}
                  </div>
                  <h3 className="display mt-1.5 text-xl text-ink md:text-2xl">{n.title}</h3>
                  <p className="mt-1.5 max-w-md text-sm leading-relaxed text-ink2/70">{n.body}</p>
                </motion.div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
