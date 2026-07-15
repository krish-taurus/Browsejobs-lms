"use client";

import { useEffect, useRef, useState } from "react";
import { useInView, useReducedMotion } from "framer-motion";

const GLYPHS = "!<>-_\\/[]{}—=+*^?#$%&010101";

/**
 * Mono "decode" effect: characters scramble and resolve left-to-right into the
 * target text — the visual metaphor for reverse-engineering. Runs once when in
 * view; renders statically under prefers-reduced-motion.
 */
export function DecodeText({
  text,
  className = "",
  delayMs = 250,
}: {
  text: string;
  className?: string;
  delayMs?: number;
}) {
  const ref = useRef<HTMLSpanElement>(null);
  const inView = useInView(ref, { once: true, margin: "-40px" });
  const reduce = useReducedMotion();
  const [display, setDisplay] = useState(text);
  const [started, setStarted] = useState(false);

  useEffect(() => {
    if (!inView || reduce || started) {
      if (reduce) setDisplay(text);
      return;
    }
    setStarted(true);

    let frame = 0;
    const totalFrames = Math.max(28, text.length * 2.2);
    let raf: number;
    const timer = window.setTimeout(() => {
      const tick = () => {
        frame++;
        const resolved = Math.floor((frame / totalFrames) * text.length);
        let out = "";
        for (let i = 0; i < text.length; i++) {
          if (i < resolved || text[i] === " ") {
            out += text[i];
          } else {
            out += GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
          }
        }
        setDisplay(out);
        if (frame < totalFrames) {
          raf = requestAnimationFrame(tick);
        } else {
          setDisplay(text);
        }
      };
      raf = requestAnimationFrame(tick);
    }, delayMs);

    return () => {
      window.clearTimeout(timer);
      cancelAnimationFrame(raf);
    };
  }, [inView, reduce, started, text, delayMs]);

  return (
    <span ref={ref} className={className} aria-label={text}>
      {display}
    </span>
  );
}
