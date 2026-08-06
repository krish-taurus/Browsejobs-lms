"use client";

import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { InkPanel } from "@/components/employers/ui";
import { StepVisual } from "@/components/employers/StepVisuals";
import { steps } from "@/content/employers";

/**
 * The seven-step walkthrough, built as a tablist: the steps are tabs, the
 * product frame is the panel. That gives arrow-key navigation and a correct
 * reading order for free — a row of divs with onClick would have looked the
 * same and been unusable without a mouse.
 *
 * On small screens the frame moves under the step list rather than beside it,
 * and the list stays a tablist so the interaction does not change shape.
 */
export function Walkthrough() {
  const [active, setActive] = useState(0);
  const reduce = useReducedMotion();
  const step = steps[active];

  const onKey = (e: React.KeyboardEvent) => {
    const last = steps.length - 1;
    if (e.key === "ArrowDown" || e.key === "ArrowRight") {
      e.preventDefault();
      setActive((i) => (i === last ? 0 : i + 1));
    } else if (e.key === "ArrowUp" || e.key === "ArrowLeft") {
      e.preventDefault();
      setActive((i) => (i === 0 ? last : i - 1));
    } else if (e.key === "Home") {
      e.preventDefault();
      setActive(0);
    } else if (e.key === "End") {
      e.preventDefault();
      setActive(last);
    }
  };

  return (
    <div className="mt-12 grid gap-6 lg:grid-cols-[1fr_0.9fr] lg:gap-10">
      {/* Steps ------------------------------------------------------- */}
      <div
        role="tablist"
        aria-label="How hiring on BrowseJobs works, step by step"
        aria-orientation="vertical"
        onKeyDown={onKey}
        className="space-y-2"
      >
        {steps.map((s, i) => {
          const on = i === active;
          return (
            <button
              key={s.n}
              role="tab"
              id={`step-tab-${s.n}`}
              aria-selected={on}
              aria-controls={`step-panel-${s.n}`}
              tabIndex={on ? 0 : -1}
              onClick={() => setActive(i)}
              className={`w-full rounded-[18px] border px-5 py-4 text-left transition-all duration-300 ${
                on
                  ? "border-trust/40 bg-white shadow-soft"
                  : "border-line bg-white/60 hover:border-trust/25 hover:bg-white"
              }`}
            >
              <div className="flex items-baseline gap-4">
                <span
                  className={`mono shrink-0 text-[12px] tracking-[0.1em] transition-colors ${
                    on ? "text-trust" : "text-muted"
                  }`}
                >
                  {s.n}
                </span>
                <span className="min-w-0 flex-1">
                  <span
                    className={`display block text-lg transition-colors ${
                      on ? "text-ink" : "text-ink2/70"
                    }`}
                  >
                    {s.title}
                  </span>

                  <AnimatePresence initial={false}>
                    {on && (
                      <motion.span
                        key="body"
                        initial={reduce ? false : { opacity: 0, height: 0 }}
                        animate={{ opacity: 1, height: "auto" }}
                        exit={reduce ? undefined : { opacity: 0, height: 0 }}
                        transition={{ duration: durations.slow, ease }}
                        className="block overflow-hidden"
                      >
                        <span className="mt-2 block text-[14px] leading-relaxed text-ink2/75">
                          {s.body}
                        </span>
                        <span className="mt-3 flex flex-wrap gap-1.5">
                          {s.detail.map((d) => (
                            <span
                              key={d}
                              className="mono rounded-full border border-line px-2.5 py-1 text-[10px] text-muted"
                            >
                              {d}
                            </span>
                          ))}
                        </span>
                      </motion.span>
                    )}
                  </AnimatePresence>
                </span>
              </div>
            </button>
          );
        })}
      </div>

      {/* Product frame ----------------------------------------------- */}
      <div className="lg:sticky lg:top-24 lg:self-start">
        <InkPanel accent="employer" className="p-5 md:p-6">
          <div
            role="tabpanel"
            id={`step-panel-${step.n}`}
            aria-labelledby={`step-tab-${step.n}`}
            className="min-h-[360px]"
          >
            <AnimatePresence mode="wait" initial={false}>
              <motion.div
                key={step.visual}
                initial={reduce ? false : { opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                exit={reduce ? undefined : { opacity: 0, y: -8 }}
                transition={{ duration: durations.slow, ease }}
                className="h-full"
              >
                <StepVisual kind={step.visual} />
              </motion.div>
            </AnimatePresence>
          </div>
        </InkPanel>
        <p className="mono mt-3 text-center text-[10px] uppercase tracking-[0.14em] text-muted">
          Step {step.n} of {steps.length < 10 ? `0${steps.length}` : steps.length} · illustrative,
          with fictional data
        </p>
      </div>
    </div>
  );
}
