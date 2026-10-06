"use client";

import { useRef } from "react";
import { motion, useScroll, useTransform, type MotionValue } from "framer-motion";
import { screenSteps } from "@/content/home";
import { ScreenFrame } from "@/components/home/ScreenFrame";

const WINDOWS: [number, number, number, number][] = [
  [0, 0.04, 0.26, 0.36],
  [0.26, 0.36, 0.6, 0.7],
  [0.6, 0.7, 0.96, 1],
];

/**
 * Desktop sticky scene: one viewport stays pinned while scroll crossfades
 * interview → score → decision. Mobile and reduced-motion get a stacked cut.
 * Copy for every step is in the DOM either way.
 */
export function ScreenScroll() {
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start start", "end end"],
  });
  const rail = useTransform(scrollYProgress, [0, 1], [0.08, 1]);

  return (
    <>
      <div className="mt-12 space-y-14 lg:hidden">
        <Stack />
      </div>
      <div className="mt-12 hidden motion-reduce:lg:block">
        <Stack />
      </div>
      <div ref={ref} className="relative mt-8 hidden h-[280vh] motion-safe:lg:block">
        <div className="sticky top-0 flex h-screen items-center">
          <div className="grid w-full grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)] items-center gap-16">
            <div className="relative min-h-[280px]">
              {screenSteps.map((step, i) => (
                <CopyLayer key={step.id} index={i} progress={scrollYProgress} step={step} />
              ))}
              <div className="absolute -left-3 top-2 hidden h-40 w-px overflow-hidden bg-white/10 xl:block">
                <motion.div className="h-full w-full origin-top bg-trust" style={{ scaleY: rail }} />
              </div>
            </div>
            <div className="relative min-h-[420px]">
              {screenSteps.map((step, i) => (
                <FrameLayer key={step.mode} index={i} progress={scrollYProgress} mode={step.mode} />
              ))}
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

function Stack() {
  return (
    <>
      {screenSteps.map((step) => (
        <article key={step.id} className="scroll-mt-32">
          <p className="mono text-sm font-semibold text-trust">{step.n}</p>
          <h3 className="display mt-2 text-3xl text-fg">{step.title}</h3>
          <p className="mt-3 max-w-xl text-base leading-relaxed text-muted">{step.body}</p>
          <div className="mt-6">
            <ScreenFrame mode={step.mode} />
          </div>
        </article>
      ))}
    </>
  );
}

function CopyLayer({
  index,
  progress,
  step,
}: {
  index: number;
  progress: MotionValue<number>;
  step: (typeof screenSteps)[number];
}) {
  const opacity = useTransform(progress, WINDOWS[index], index === 0 ? [1, 1, 1, 0] : index === 2 ? [0, 1, 1, 1] : [0, 1, 1, 0]);
  const y = useTransform(progress, WINDOWS[index], index === 0 ? [0, 0, 0, -18] : [18, 0, 0, -18]);
  return (
    <motion.article style={{ opacity, y }} className="absolute inset-0 flex flex-col justify-center">
      <p className="mono text-sm font-semibold text-trust">{step.n}</p>
      <h3 className="display mt-3 text-4xl text-fg xl:text-5xl">{step.title}</h3>
      <p className="mt-4 max-w-md text-lg leading-relaxed text-muted">{step.body}</p>
    </motion.article>
  );
}

function FrameLayer({
  index,
  progress,
  mode,
}: {
  index: number;
  progress: MotionValue<number>;
  mode: (typeof screenSteps)[number]["mode"];
}) {
  const opacity = useTransform(progress, WINDOWS[index], index === 0 ? [1, 1, 1, 0] : index === 2 ? [0, 1, 1, 1] : [0, 1, 1, 0]);
  const y = useTransform(progress, WINDOWS[index], [12, 0, 0, -12]);
  return (
    <motion.div style={{ opacity, y }} className="absolute inset-0 flex items-center">
      <div className="w-full">
        <ScreenFrame mode={mode} />
      </div>
    </motion.div>
  );
}
