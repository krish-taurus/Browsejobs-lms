"use client";

import { useEffect, useRef } from "react";
import { prefersReducedMotion } from "@/lib/motion";

/**
 * A short, silent motion-graphics loop (rendered with HyperFrames) used as
 * decoration. It downloads and plays only while on screen, shows no controls,
 * and stays on its poster for reduced-motion visitors. The meaning it shows
 * must also be in the page text, so it is hidden from screen readers.
 */
export function ApLoop({ src, poster, className }: { src: string; poster: string; className?: string }) {
  const ref = useRef<HTMLVideoElement | null>(null);
  useEffect(() => {
    const v = ref.current;
    if (!v || prefersReducedMotion()) return;
    const io = new IntersectionObserver(
      ([e]) => {
        if (e?.isIntersecting) void v.play().catch(() => undefined);
        else v.pause();
      },
      { threshold: 0.2 },
    );
    io.observe(v);
    return () => io.disconnect();
  }, []);
  return <video ref={ref} className={className} src={src} poster={poster} muted loop playsInline preload="none" aria-hidden="true" tabIndex={-1} />;
}
