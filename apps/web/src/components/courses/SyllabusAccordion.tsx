"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import type { CourseModuleContent } from "@/content/courses";

/** Module-by-module syllabus with expandable topic lists. */
export function SyllabusAccordion({ modules }: { modules: CourseModuleContent[] }) {
  const [open, setOpen] = useState<number | null>(0);
  const reduce = useReducedMotion();

  return (
    <div className="divide-y divide-line rounded-[14px] border border-line bg-white">
      {modules.map((m, i) => {
        const isOpen = open === i;
        return (
          <div key={m.title}>
            <button
              onClick={() => setOpen(isOpen ? null : i)}
              aria-expanded={isOpen}
              className="flex w-full items-center gap-4 px-5 py-4 text-left"
            >
              <span className="mono shrink-0 text-sm font-semibold text-trust">
                {String(i + 1).padStart(2, "0")}
              </span>
              <span className="flex-1">
                <span className="display block text-base text-ink">{m.title}</span>
                <span className="mt-0.5 block text-sm text-muted">{m.hook}</span>
              </span>
              <span
                className={`mono shrink-0 text-trust transition-transform ${isOpen ? "rotate-45" : ""}`}
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
                  <ul className="space-y-2 px-5 pb-5 pl-[3.25rem]">
                    {m.topics.map((t) => (
                      <li key={t} className="flex gap-2.5 text-sm text-ink2/85">
                        <span className="mt-2 h-1 w-1 shrink-0 rounded-full bg-trust" />
                        {t}
                      </li>
                    ))}
                  </ul>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        );
      })}
    </div>
  );
}
