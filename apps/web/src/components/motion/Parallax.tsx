"use client";

import { useRef, type ReactNode } from "react";
import { motion, useReducedMotion, useScroll, useTransform } from "framer-motion";

/**
 * Gentle scroll-linked drift: the child translates a few pixels against the
 * scroll direction, giving the page physical depth. Scroll-linked (never
 * autonomous), transform-only, and the offset is zeroed under reduced motion
 * while the DOM stays identical (hydration-safe).
 */
export function Parallax({
  children,
  distance = 28,
  className = "",
}: {
  children: ReactNode;
  /** Total travel in px across the element's scroll range. */
  distance?: number;
  className?: string;
}) {
  const reduce = useReducedMotion();
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ["start end", "end start"] });
  const y = useTransform(scrollYProgress, [0, 1], [distance / 2, -distance / 2]);

  return (
    <motion.div ref={ref} className={className} style={{ y: reduce ? 0 : y }}>
      {children}
    </motion.div>
  );
}
