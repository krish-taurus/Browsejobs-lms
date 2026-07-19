"use client";

import { motion, useScroll, useSpring } from "framer-motion";

/**
 * A hairline of trust blue along the very top of the viewport that fills with
 * reading progress — quiet wayfinding for a long marketing page. Scroll-linked
 * (never autonomous); hidden via CSS under reduced motion so the DOM stays
 * identical between server and client (hydration-safe).
 */
export function ScrollProgress() {
  const { scrollYProgress } = useScroll();
  const scaleX = useSpring(scrollYProgress, { stiffness: 140, damping: 28, restDelta: 0.001 });

  return (
    <motion.div
      aria-hidden
      className="fixed inset-x-0 top-0 z-[60] h-[2px] origin-left bg-trust motion-reduce:hidden"
      style={{ scaleX }}
    />
  );
}
