"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { InkPanel } from "@/components/employers/ui";
import { StepVisual } from "@/components/employers/StepVisuals";
import { steps } from "@/content/employers";

/** How long a step holds before the walkthrough moves on, and the tick. */
const DWELL_MS = 6500;
const TICK_MS = 100;

/**
 * The seven-step walkthrough. It plays itself, so a visitor who scrolls to it
 * and does nothing still sees the whole product; the moment they touch it,
 * it stops and stays where they put it.
 *
 * Built as a tablist rather than a row of divs, so arrow keys, Home and End
 * work and the panel is announced against the step that owns it. Autoplay
 * pauses on hover, on focus and permanently on any explicit choice — an
 * animation that keeps yanking the panel away while someone is reading is
 * worse than no animation.
 *
 * Under reduced motion nothing advances on its own at all.
 */
export function Walkthrough() {
  const reduce = useReducedMotion();
  const [active, setActive] = useState(0);
  const [elapsed, setElapsed] = useState(0);
  const [hovering, setHovering] = useState(false);
  const takenOver = useRef(false);

  const playing = !reduce && !hovering && !takenOver.current;

  useEffect(() => {
    if (!playing) return;
    const t = window.setInterval(() => {
      setElapsed((e) => {
        if (e + TICK_MS < DWELL_MS) return e + TICK_MS;
        setActive((i) => (i + 1) % steps.length);
        return 0;
      });
    }, TICK_MS);
    return () => window.clearInterval(t);
  }, [playing]);

  /** Any explicit choice ends autoplay for good — the visitor is driving now. */
  const choose = useCallback((i: number) => {
    takenOver.current = true;
    setActive(i);
    setElapsed(0);
  }, []);

  const onKey = (e: React.KeyboardEvent) => {
    const last = steps.length - 1;
    if (e.key === "ArrowDown" || e.key === "ArrowRight") {
      e.preventDefault();
      choose(active === last ? 0 : active + 1);
    } else if (e.key === "ArrowUp" || e.key === "ArrowLeft") {
      e.preventDefault();
      choose(active === 0 ? last : active - 1);
    } else if (e.key === "Home") {
      e.preventDefault();
      choose(0);
    } else if (e.key === "End") {
      e.preventDefault();
      choose(last);
    }
  };

  const step = steps[active];

  return (
    <div
      className="mt-12 grid gap-6 lg:grid-cols-[1fr_0.9fr] lg:gap-10"
      onPointerEnter={() => setHovering(true)}
      onPointerLeave={() => setHovering(false)}
      onFocusCapture={() => setHovering(true)}
      onBlurCapture={() => setHovering(false)}
    >
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
              onClick={() => choose(i)}
              className={`relative w-full overflow-hidden rounded-[18px] border px-5 py-4 text-left transition-all duration-300 ${
                on
                  ? "border-white/20 bg-white/[0.05]"
                  : "border-white/[0.07] bg-white/[0.02] hover:border-white/15 hover:bg-white/[0.04]"
              }`}
            >
              <div className="flex items-baseline gap-4">
                <span
                  className={`mono shrink-0 text-[12px] tracking-[0.1em] transition-colors ${
                    on ? "text-trust" : "text-white/30"
                  }`}
                >
                  {s.n}
                </span>
                <span className="min-w-0 flex-1">
                  <span
                    className={`display block text-lg transition-colors ${
                      on ? "text-white" : "text-white/55"
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
                        <span className="mt-2 block text-[14px] leading-relaxed text-white/60">
                          {s.body}
                        </span>
                        <span className="mt-3 flex flex-wrap gap-1.5">
                          {s.detail.map((d) => (
                            <span
                              key={d}
                              className="mono rounded-full border border-white/10 px-2.5 py-1 text-[10px] text-white/45"
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

              {/* Dwell bar: how long this step holds before the next one.
                  Absent entirely once the visitor has taken over. */}
              {on && playing && (
                <span
                  aria-hidden
                  className="absolute inset-x-0 bottom-0 h-[2px] bg-gradient-to-r from-trust to-employer"
                  style={{ width: `${Math.min(100, (elapsed / DWELL_MS) * 100)}%` }}
                />
              )}
            </button>
          );
        })}
      </div>

      {/* Product frame ----------------------------------------------- */}
      <div className="lg:sticky lg:top-24 lg:self-start">
        <InkPanel accent="employer" live className="p-5 md:p-6">
          <div
            role="tabpanel"
            id={`step-panel-${step.n}`}
            aria-labelledby={`step-tab-${step.n}`}
            className="min-h-[360px]"
          >
            {/* Keyed remount rather than AnimatePresence: `mode="wait"` held
                the outgoing visual's exit before mounting the next one, which
                left the frame empty for a beat — on a panel that advances by
                itself that reads as a broken screenshot, not a transition. */}
            <motion.div
              key={step.visual}
              initial={reduce ? false : { opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: durations.slow, ease }}
              className="h-full"
            >
              <StepVisual kind={step.visual} />
            </motion.div>
          </div>
        </InkPanel>
        <p className="mono mt-3 text-center text-[10px] uppercase tracking-[0.14em] text-white/35">
          Step {step.n} of 07 · illustrative, with fictional data
        </p>
      </div>
    </div>
  );
}
