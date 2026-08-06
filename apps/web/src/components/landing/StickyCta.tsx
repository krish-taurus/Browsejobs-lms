"use client";

import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { openLeadModal } from "@/components/landing/leadModalBus";

/**
 * Sticky mobile CTA bar (spec §6.1). Appears after the hero scrolls away.
 *
 * The employer variant exists because the default bar offers a free
 * masterclass — the wrong thing to put in front of a hiring manager on the
 * one page written for them. It scrolls to that page's enquiry form instead.
 */
export function StickyCta({ variant = "default" }: { variant?: "default" | "employer" } = {}) {
  const [visible, setVisible] = useState(false);
  const reduce = useReducedMotion();

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > 480);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  const employer = variant === "employer";

  const onClick = () => {
    if (!employer) {
      openLeadModal({ variant: "masterclass" });
      return;
    }
    // Lenis swallows native hash jumps, so scroll explicitly.
    document.getElementById("hire")?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  return (
    <AnimatePresence>
      {visible && (
        <motion.div
          initial={reduce ? false : { y: 72 }}
          animate={{ y: 0 }}
          exit={reduce ? undefined : { y: 72 }}
          transition={{ duration: durations.base, ease }}
          className={`fixed inset-x-0 bottom-0 z-40 border-t p-3 backdrop-blur-md md:hidden ${
            employer ? "border-white/10 bg-deck-card/95" : "border-line bg-white/95"
          }`}
        >
          <button
            onClick={onClick}
            className="w-full rounded-full bg-trust py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)]"
          >
            {employer ? "Start free for 6 months" : "Book Free Masterclass"}
          </button>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
