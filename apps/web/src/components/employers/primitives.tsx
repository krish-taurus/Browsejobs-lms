"use client";

/**
 * Shared motion primitives for the /employers landing page. Keeps the keynote
 * art direction of the v3 home page (huge tracking-tight type on white, dark
 * product scenes between) while honouring CLAUDE.md §Motion: one signature
 * easing from `lib/motion`, transform+opacity only, and `prefers-reduced-motion`
 * disabling every animation — including the looping demo ambience.
 */

import { useEffect, useRef } from "react";
import Lenis from "lenis";
import { animate, motion, useInView, useMotionValue, useReducedMotion, useSpring } from "framer-motion";
import { ease, durations } from "@/lib/motion";

export { ease, durations };

/** True when the visitor has not asked for reduced motion. */
export function useMotionOk(): boolean {
  return !useReducedMotion();
}

/** Smooth-scroll the page, unless the visitor prefers reduced motion. */
export function useSmoothScroll(): void {
  const ok = useMotionOk();
  useEffect(() => {
    if (!ok) return;
    const lenis = new Lenis({ lerp: 0.08 });
    let raf = 0;
    const loop = (t: number) => {
      lenis.raf(t);
      raf = requestAnimationFrame(loop);
    };
    raf = requestAnimationFrame(loop);
    return () => {
      cancelAnimationFrame(raf);
      lenis.destroy();
    };
  }, [ok]);
}

/** Button wrapper that leans toward the cursor. No-op under reduced motion. */
export function Magnetic({ children }: { children: React.ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  const ok = useMotionOk();
  const x = useMotionValue(0);
  const y = useMotionValue(0);
  const sx = useSpring(x, { stiffness: 200, damping: 15 });
  const sy = useSpring(y, { stiffness: 200, damping: 15 });

  if (!ok) return <div className="inline-block">{children}</div>;

  return (
    <motion.div
      ref={ref}
      style={{ x: sx, y: sy }}
      onMouseMove={(e) => {
        const r = ref.current!.getBoundingClientRect();
        x.set((e.clientX - r.left - r.width / 2) * 0.3);
        y.set((e.clientY - r.top - r.height / 2) * 0.3);
      }}
      onMouseLeave={() => {
        x.set(0);
        y.set(0);
      }}
      className="inline-block"
    >
      {children}
    </motion.div>
  );
}

/** Mono counter that ticks up once, when scrolled into view. */
export function Counter({ to, suffix = "", prefix = "" }: { to: number; suffix?: string; prefix?: string }) {
  const ref = useRef<HTMLSpanElement>(null);
  const inView = useInView(ref, { once: true, margin: "-80px" });
  const ok = useMotionOk();

  useEffect(() => {
    if (!inView || !ref.current) return;
    const el = ref.current;
    if (!ok) {
      el.textContent = prefix + String(to) + suffix;
      return;
    }
    const controls = animate(0, to, {
      duration: durations.slower,
      ease,
      onUpdate: (v) => {
        el.textContent = prefix + String(Math.round(v)) + suffix;
      },
    });
    return () => controls.stop();
  }, [inView, to, suffix, prefix, ok]);

  return (
    <span ref={ref} className="mono">
      {prefix}0{suffix}
    </span>
  );
}

/** Scroll reveal: fade + 18px rise, once (spec §2.4). */
export function Reveal({
  children,
  delay = 0,
  className = "",
  as = "div",
}: {
  children: React.ReactNode;
  delay?: number;
  className?: string;
  as?: "div" | "li" | "p" | "section";
}) {
  const ok = useMotionOk();
  const Tag = motion[as];
  return (
    <Tag
      initial={ok ? { opacity: 0, y: 18 } : false}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-80px" }}
      transition={{ duration: durations.slow, ease, delay }}
      className={className}
    >
      {children}
    </Tag>
  );
}

/** Mono uppercase kicker — the brand signature label. */
export function Kicker({ children, color }: { children: React.ReactNode; color?: string }) {
  return (
    <p className="kicker" style={color ? { color } : undefined}>
      {children}
    </p>
  );
}

/**
 * A dark full-bleed product scene: copy on one side, a self-demonstrating
 * device on the other, tinted by the stage accent.
 */
export function Scene({
  id,
  step,
  kicker,
  title,
  body,
  points,
  accent,
  demo,
  flip = false,
}: {
  id: string;
  step: string;
  kicker: string;
  title: string;
  body: string;
  points: readonly string[];
  accent: string;
  demo: React.ReactNode;
  flip?: boolean;
}) {
  const ok = useMotionOk();
  return (
    <section id={id} className="relative overflow-clip bg-ink py-20 text-white md:py-28">
      <div
        aria-hidden
        className="absolute left-1/2 top-0 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-white/20 to-transparent"
      />
      <div
        aria-hidden
        className="absolute left-1/2 top-1/2 h-[500px] w-[700px] -translate-x-1/2 -translate-y-1/2 rounded-full blur-[160px]"
        style={{ background: accent, opacity: 0.16 }}
      />
      <div
        className={`relative mx-auto flex max-w-6xl flex-col items-center gap-14 px-6 md:gap-20 ${
          flip ? "md:flex-row-reverse" : "md:flex-row"
        }`}
      >
        <div className="flex-1">
          <Reveal>
            <div className="flex items-center gap-3">
              <span
                className="mono grid h-9 w-9 place-items-center rounded-full text-xs font-semibold"
                style={{ background: `${accent}24`, color: accent }}
              >
                {step}
              </span>
              <Kicker color={accent}>{kicker}</Kicker>
            </div>
          </Reveal>
          <Reveal delay={0.06}>
            <h2 className="display mt-5 text-3xl leading-[1.06] md:text-5xl">{title}</h2>
          </Reveal>
          <Reveal delay={0.12}>
            <p className="mt-5 max-w-md text-base leading-relaxed text-white/60 md:text-lg">{body}</p>
          </Reveal>
          <ul className="mt-7 space-y-3">
            {points.map((p, i) => (
              <Reveal as="li" key={p} delay={0.18 + i * 0.06} className="flex items-start gap-3">
                <svg viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4 w-4 shrink-0" style={{ color: accent }}>
                  <path
                    d="M5 12.5l4.5 4.5L19 7.5"
                    stroke="currentColor"
                    strokeWidth="2.2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
                <span className="text-sm leading-relaxed text-white/70">{p}</span>
              </Reveal>
            ))}
          </ul>
        </div>
        <motion.div
          initial={ok ? { opacity: 0, y: 40, scale: 0.96 } : false}
          whileInView={{ opacity: 1, y: 0, scale: 1 }}
          viewport={{ once: true, margin: "-10%" }}
          transition={{ duration: durations.slower, ease }}
          className="w-full flex-1"
        >
          {demo}
        </motion.div>
      </div>
    </section>
  );
}
