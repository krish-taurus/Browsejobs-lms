"use client";

import { createContext, useContext, useEffect, useLayoutEffect, useRef, useState, type ReactNode } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { isMobileViewport, prefersReducedMotion, type SceneId } from "@/lib/motion";
import { setSceneState } from "@/lib/scene-bus";

if (typeof window !== "undefined") gsap.registerPlugin(ScrollTrigger);

const TimelineContext = createContext<gsap.core.Timeline | null>(null);

export function useSceneTimeline(): gsap.core.Timeline | null {
  return useContext(TimelineContext);
}

/** Adds tweens to the pinned scrub timeline. No-op when pinning is off. */
export function useScrub(build: (timeline: gsap.core.Timeline) => void): void {
  const timeline = useSceneTimeline();
  const buildRef = useRef(build);
  buildRef.current = build;
  useEffect(() => {
    if (!timeline) return;
    buildRef.current(timeline);
    ScrollTrigger.refresh();
  }, [timeline]);
}

/**
 * Pins a section for 120% of the viewport and exposes a scrub:1 timeline.
 * Pinning is skipped under reduced motion and below 768px.
 */
export function ScenePin({
  scene,
  className,
  shot,
  children,
}: {
  scene?: SceneId;
  className?: string;
  shot?: string;
  children: ReactNode;
}) {
  const ref = useRef<HTMLElement>(null);
  const [timeline, setTimeline] = useState<gsap.core.Timeline | null>(null);

  useLayoutEffect(() => {
    const node = ref.current;
    if (!node || prefersReducedMotion() || isMobileViewport()) return;
    const next = gsap.timeline({
      scrollTrigger: {
        trigger: node,
        start: "top top",
        end: "+=120%",
        pin: true,
        scrub: 1,
        onUpdate: (self) => {
          if (scene) setSceneState(scene, self.progress);
        },
      },
    });
    setTimeline(next);
    return () => {
      next.scrollTrigger?.kill();
      next.kill();
      setTimeline(null);
    };
  }, [scene]);

  return (
    <section ref={ref} className={className} data-scene={scene} data-shot={shot} data-pinned={timeline ? "true" : undefined}>
      <TimelineContext.Provider value={timeline}>{children}</TimelineContext.Provider>
    </section>
  );
}

export function SceneTransition({ from, to }: { from: ReactNode; to: ReactNode }) {
  const ref = useRef<HTMLElement>(null);
  const outgoing = useRef<HTMLDivElement>(null);
  const incoming = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    const out = outgoing.current;
    const inn = incoming.current;
    if (!out || !inn) return;
    if (prefersReducedMotion() || isMobileViewport()) {
      gsap.set(out, { opacity: 1, filter: "none", scale: 1 });
      gsap.set(inn, { opacity: 1, filter: "none", scale: 1, position: "relative" });
      return;
    }
    const timeline = gsap.timeline({
      scrollTrigger: {
        trigger: ref.current,
        start: "top top",
        end: "+=120%",
        pin: true,
        scrub: 1,
      },
    });
    timeline.fromTo(
      out,
      { filter: "blur(0px)", opacity: 1, scale: 1 },
      { filter: "blur(16px)", opacity: 0, scale: 0.96, duration: 0.7, ease: "none" },
      0,
    );
    timeline.fromTo(
      inn,
      { filter: "blur(16px)", opacity: 0, scale: 1.04 },
      { filter: "blur(0px)", opacity: 1, scale: 1, duration: 0.7, ease: "none" },
      0.3,
    );
    return () => {
      timeline.scrollTrigger?.kill();
      timeline.kill();
    };
  }, []);

  return (
    <section ref={ref} className="argus-transition">
      <div ref={outgoing} className="argus-transition-pane">
        {from}
      </div>
      <div ref={incoming} className="argus-transition-pane">
        {to}
      </div>
    </section>
  );
}
