"use client";

import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { openLeadModal, type LeadVariant } from "@/components/landing/leadModalBus";

/** Sticky mobile CTA bar (spec §6.1). Appears after the hero scrolls away. */
export function StickyCta({
  label = "Book Free Masterclass",
  variant = "masterclass",
  href,
  tone = "light",
}: {
  label?: string;
  variant?: LeadVariant;
  href?: string;
  tone?: "light" | "night";
} = {}) {
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
          className={`fixed inset-x-0 bottom-0 z-40 border-t p-3 backdrop-blur-md md:hidden ${
            tone === "night" ? "border-white/10 bg-ink/95" : "border-line bg-white/95"
          }`}
        >
          {href ? (
            <a
              href={href}
              className="block w-full rounded-full bg-trust py-3 text-center font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)]"
            >
              {label}
            </a>
          ) : (
            <button
              onClick={() => openLeadModal({ variant })}
              className="w-full rounded-full bg-trust py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)]"
            >
              {label}
            </button>
          )}
        </motion.div>
      )}
    </AnimatePresence>
  );
}
