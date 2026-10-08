"use client";

import { Children, useEffect, useRef, type ReactNode } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { prefersReducedMotion } from "@/lib/motion";

if (typeof window !== "undefined") gsap.registerPlugin(ScrollTrigger);

export function Reveal({
  heading,
  body,
  children,
}: {
  heading?: ReactNode;
  body?: ReactNode;
  children?: ReactNode;
}) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const root = ref.current;
    if (!root) return;
    const head = root.querySelector("[data-reveal='heading']");
    const copy = root.querySelector("[data-reveal='body']");
    const kids = root.querySelectorAll("[data-reveal='child']");
    const nodes = [head, copy, ...kids].filter(Boolean);
    if (prefersReducedMotion()) {
      gsap.set(nodes, { opacity: 1, y: 0, filter: "none" });
      return;
    }
    const timeline = gsap.timeline({ scrollTrigger: { trigger: root, start: "top 80%" } });
    const from = { y: 30, opacity: 0, filter: "blur(10px)", duration: 0.9, ease: "power3.out" };
    if (head) timeline.from(head, from, 0);
    if (copy) timeline.from(copy, from, 0.1);
    if (kids.length) timeline.from(kids, { ...from, stagger: 0.08 }, 0.18);
    return () => {
      timeline.scrollTrigger?.kill();
      timeline.kill();
    };
  }, []);

  return (
    <div ref={ref}>
      {heading ? <div data-reveal="heading">{heading}</div> : null}
      {body ? <div data-reveal="body">{body}</div> : null}
      {Children.map(children, (child, index) => (
        <div data-reveal="child" key={index}>
          {child}
        </div>
      ))}
    </div>
  );
}
