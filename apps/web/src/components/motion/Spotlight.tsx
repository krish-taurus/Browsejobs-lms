"use client";

import { useRef, useState, type ReactNode } from "react";
import { motion, useMotionValue, useReducedMotion, useSpring } from "framer-motion";
import { durations, ease } from "@/lib/motion";

/**
 * Pointer spotlight for ink panels: a soft trust-blue radial glow trails the
 * cursor across the dark surface, like a torch over the machine. The glow is a
 * single translated element (transform/opacity only) so it costs nothing to
 * paint. Invisible on touch and under reduced motion.
 */
export function Spotlight({
  children,
  className = "",
  backdrop,
}: {
  children: ReactNode;
  className?: string;
  /** Optional full-bleed art layer rendered behind the content (e.g. panel artwork). */
  backdrop?: ReactNode;
}) {
  const reduce = useReducedMotion();
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  const x = useMotionValue(0);
  const y = useMotionValue(0);
  const sx = useSpring(x, { stiffness: 160, damping: 26 });
  const sy = useSpring(y, { stiffness: 160, damping: 26 });

  // Same DOM in both modes (hydration-safe); under reduced motion the glow
  // simply never becomes visible.
  const onMove = (e: React.PointerEvent) => {
    if (reduce || e.pointerType !== "mouse" || !ref.current) return;
    const r = ref.current.getBoundingClientRect();
    x.set(e.clientX - r.left - 300);
    y.set(e.clientY - r.top - 300);
    setVisible(true);
  };

  return (
    <div
      ref={ref}
      className={`relative overflow-hidden ${className}`}
      onPointerMove={onMove}
      onPointerLeave={() => setVisible(false)}
    >
      {backdrop}
      <motion.div
        aria-hidden
        className="pointer-events-none absolute left-0 top-0 -z-0 h-[600px] w-[600px]"
        style={{
          x: sx,
          y: sy,
          background:
            "radial-gradient(circle at center, rgba(27,109,240,0.16) 0%, rgba(27,109,240,0.05) 40%, transparent 70%)",
        }}
        animate={{ opacity: visible ? 1 : 0 }}
        transition={{ duration: durations.base, ease }}
      />
      <div className="relative">{children}</div>
    </div>
  );
}
