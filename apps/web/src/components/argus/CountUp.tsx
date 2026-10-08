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

  const final = `${prefix}${to.toFixed(decimals)}${suffix}`;

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (prefersReducedMotion()) {
      el.textContent = final;
      return;
    }
    const state = { n: from };
    const tween = gsap.to(state, {
      n: to,
      duration: 1.2,
      ease: "power3.out",
      immediateRender: false,
      scrollTrigger: { trigger: el, start: "top 90%" },
      onUpdate: () => {
        el.textContent = `${prefix}${state.n.toFixed(decimals)}${suffix}`;
      },
    });
    return () => {
      tween.scrollTrigger?.kill();
      tween.kill();
    };
  }, [from, to, decimals, suffix, prefix, final]);

  return (
    <span ref={ref} className={className ? `argus-count ${className}` : "argus-count"}>
      {final}
    </span>
  );
}
