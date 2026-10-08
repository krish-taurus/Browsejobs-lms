"use client";

import { useEffect, useRef } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { prefersReducedMotion } from "@/lib/motion";

if (typeof window !== "undefined") gsap.registerPlugin(ScrollTrigger);

export function SplitHeading({
  as: Tag = "h2",
  text,
  className,
  breakAfter,
}: {
  as?: "h1" | "h2";
  text: string;
  className?: string;
  /** Insert a line break after this word index. The space stays in the accessible name. */
  breakAfter?: number;
}) {
  const ref = useRef<HTMLHeadingElement>(null);
  const words = text.split(/\s+/).filter(Boolean);

  useEffect(() => {
    const spans = ref.current?.querySelectorAll("[data-word]");
    if (!spans?.length) return;
    if (prefersReducedMotion()) {
      gsap.set(spans, { opacity: 1, y: 0, filter: "none" });
      return;
    }
    const tween = gsap.from(spans, {
      y: 28,
      opacity: 0,
      filter: "blur(10px)",
      duration: 0.9,
      ease: "power3.out",
      stagger: 0.06,
      scrollTrigger: { trigger: ref.current, start: "top 90%" },
    });
    ScrollTrigger.refresh();
    return () => {
      tween.scrollTrigger?.kill();
      tween.kill();
    };
  }, [text]);

  return (
    <Tag ref={ref} className={className}>
      {words.map((word, index) => (
        <span key={`${word}-${index}`}>
          <span data-word style={{ display: "inline-block" }}>
            {word}
          </span>
          {index === breakAfter ? <br /> : null}
          {index < words.length - 1 ? " " : null}
        </span>
      ))}
    </Tag>
  );
}
