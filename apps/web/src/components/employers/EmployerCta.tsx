"use client";

import type { ReactNode } from "react";
import { Magnetic } from "@/components/motion/Magnetic";

/**
 * The page's primary action. It scrolls to the enquiry form rather than
 * navigating, and it does the scroll explicitly: MarketingShell runs Lenis,
 * which swallows native hash jumps, so a plain `href="#hire"` lands nowhere.
 */
export function EmployerCta({
  children,
  ghost = false,
  className = "",
}: {
  children: ReactNode;
  ghost?: boolean;
  className?: string;
}) {
  const skin = ghost
    ? "rounded-full border border-line bg-white px-6 py-3 font-semibold text-ink transition-colors hover:border-trust hover:text-trust"
    : "group inline-flex items-center justify-center gap-2.5 rounded-full bg-trust px-6 py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-all duration-300 hover:bg-deep hover:shadow-[0_8px_32px_rgba(27,109,240,0.45)] active:scale-[0.98]";

  const go = () => {
    const el = document.getElementById("hire");
    if (!el) return;
    requestAnimationFrame(() => el.scrollIntoView({ behavior: "smooth", block: "start" }));
    history.replaceState(null, "", "#hire");
    // Land on the first field, so keyboard users are not dropped at the top
    // of a form they then have to tab into.
    window.setTimeout(() => document.getElementById("employer-company")?.focus(), 700);
  };

  const button = (
    <button type="button" onClick={go} className={`${skin} ${className}`}>
      {children}
      {!ghost && (
        <span
          aria-hidden
          className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm transition-transform duration-300 group-hover:translate-x-0.5 group-hover:scale-105"
        >
          →
        </span>
      )}
    </button>
  );

  return ghost ? button : <Magnetic>{button}</Magnetic>;
}
