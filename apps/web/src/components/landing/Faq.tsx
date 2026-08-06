"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { Kicker } from "@/components/brand/Kicker";
import { faqs } from "@/content/landing";

/**
 * The shared FAQ accordion. It defaults to the landing page's questions, and
 * takes its own set when another page needs the same component with different
 * content — one accordion for the site rather than one per page.
 */
export function Faq({
  items = faqs,
  kicker = "Questions",
  title = "Straight answers",
  id = "faq",
}: {
  items?: readonly { q: string; a: string }[];
  kicker?: string;
  title?: string;
  id?: string;
} = {}) {
  const [open, setOpen] = useState<number | null>(0);
  const reduce = useReducedMotion();

  return (
    <section id={id} className="mx-auto max-w-3xl scroll-mt-24 px-5 py-20 md:py-28">
      <Kicker>{kicker}</Kicker>
      <h2 className="display mt-3 text-3xl text-ink md:text-5xl">{title}</h2>

      <div className="mt-10 divide-y divide-line border-y border-line">
        {items.map((f, i) => {
          const isOpen = open === i;
          return (
            <div key={f.q}>
              <button
                onClick={() => setOpen(isOpen ? null : i)}
                className="flex w-full items-center justify-between gap-4 py-5 text-left"
                aria-expanded={isOpen}
              >
                <span className="font-semibold text-ink">{f.q}</span>
                <span
                  className={`mono shrink-0 text-trust transition-transform ${
                    isOpen ? "rotate-45" : ""
                  }`}
                >
                  +
                </span>
              </button>
              <AnimatePresence initial={false}>
                {isOpen && (
                  <motion.div
                    initial={reduce ? false : { height: 0, opacity: 0 }}
                    animate={{ height: "auto", opacity: 1 }}
                    exit={reduce ? undefined : { height: 0, opacity: 0 }}
                    transition={{ duration: durations.base, ease }}
                    className="overflow-hidden"
                  >
                    <p className="pb-5 text-muted">{f.a}</p>
                  </motion.div>
                )}
              </AnimatePresence>
            </div>
          );
        })}
      </div>
    </section>
  );
}
