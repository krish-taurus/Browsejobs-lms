"use client";

import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { openLeadModal } from "@/components/landing/leadModalBus";

/** Sticky mobile CTA bar (spec §6.1). Appears after the hero scrolls away. */
export function StickyCta() {
  const [visible, setVisible] = useState(false);
  const reduce = useReducedMotion();

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > 480);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <AnimatePresence>
      {visible && (
        <motion.div
          initial={reduce ? false : { y: 72 }}
          animate={{ y: 0 }}
          exit={reduce ? undefined : { y: 72 }}
          transition={{ duration: durations.base, ease }}
          className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 p-3 backdrop-blur-md md:hidden"
        >
          <button
            onClick={() => openLeadModal({ variant: "masterclass" })}
            className="w-full rounded-full bg-trust py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)]"
          >
            Book Free Masterclass
          </button>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
