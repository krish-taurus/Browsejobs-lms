"use client";

import { useRef, type ReactNode } from "react";
import { motion, useMotionValue, useReducedMotion, useSpring, useTransform } from "framer-motion";

/**
 * Whisper-subtle 3D tilt (≤2.5°) + lift for feature cards — enough that the
 * card answers the cursor, never enough to feel like a gimmick. Transform-only;
 * static on touch and under reduced motion.
 */
export function TiltCard({ children, className = "" }: { children: ReactNode; className?: string }) {
  const reduce = useReducedMotion();
  const ref = useRef<HTMLDivElement>(null);
  const px = useMotionValue(0.5);
  const py = useMotionValue(0.5);
  const rx = useSpring(useTransform(py, [0, 1], [2.5, -2.5]), { stiffness: 220, damping: 24 });
  const ry = useSpring(useTransform(px, [0, 1], [-2.5, 2.5]), { stiffness: 220, damping: 24 });

  // Same DOM in both modes (hydration-safe); under reduced motion the
  // handlers no-op so the card stays flat.
  const onMove = (e: React.PointerEvent) => {
    if (reduce || e.pointerType !== "mouse" || !ref.current) return;
    const r = ref.current.getBoundingClientRect();
    px.set((e.clientX - r.left) / r.width);
    py.set((e.clientY - r.top) / r.height);
  };

  const onLeave = () => {
    px.set(0.5);
    py.set(0.5);
  };

  return (
    <motion.div
      ref={ref}
      className={className}
      style={{ rotateX: rx, rotateY: ry, transformPerspective: 900 }}
      onPointerMove={onMove}
      onPointerLeave={onLeave}
    >
      {children}
    </motion.div>
  );
}
