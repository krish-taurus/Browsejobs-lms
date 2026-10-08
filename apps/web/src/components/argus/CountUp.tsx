"use client";

import { useEffect, useRef } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { prefersReducedMotion } from "@/lib/motion";

if (typeof window !== "undefined") gsap.registerPlugin(ScrollTrigger);

export function CountUp({
  from = 0,
  to,
  decimals = 0,
  suffix = "",
  prefix = "",
  className,
}: {
  from?: number;
  to: number;
  decimals?: number;
  suffix?: string;
  prefix?: string;
  className?: string;
}) {
  const ref = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const state = { n: from };
    const paint = () => {
      el.textContent = `${prefix}${state.n.toFixed(decimals)}${suffix}`;
    };
    if (prefersReducedMotion()) {
      state.n = to;
      paint();
      return;
    }
    const tween = gsap.to(state, {
      n: to,
      duration: 1.2,
      ease: "power3.out",
      scrollTrigger: { trigger: el, start: "top 85%" },
      onUpdate: paint,
    });
    paint();
    return () => {
      tween.scrollTrigger?.kill();
      tween.kill();
    };
  }, [from, to, decimals, suffix, prefix]);

  return <span ref={ref} className={className ? `argus-count ${className}` : "argus-count"} />;
}
