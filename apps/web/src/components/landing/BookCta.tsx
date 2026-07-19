"use client";

import type { ReactNode } from "react";
import {
  openLeadModal,
  type LeadVariant,
} from "@/components/landing/leadModalBus";
import { Magnetic } from "@/components/motion/Magnetic";

/**
 * The one blue primary CTA (spec: one primary per view; everything funnels to
 * "Book Free Masterclass"). `ghost` renders the secondary line-border style.
 */
export function BookCta({
  children = "Book Free Masterclass",
  variant = "masterclass",
  courseSlug,
  ghost = false,
  className = "",
}: {
  children?: ReactNode;
  variant?: LeadVariant;
  courseSlug?: string;
  ghost?: boolean;
  className?: string;
}) {
  const base = ghost
    ? "rounded-full border border-line bg-white px-6 py-3 font-semibold text-ink transition-colors hover:border-trust"
    : "group inline-flex items-center justify-center gap-2.5 rounded-full bg-trust px-6 py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-all duration-300 hover:bg-deep hover:shadow-[0_8px_32px_rgba(27,109,240,0.45)] active:scale-[0.98]";

  const button = (
    <button
      type="button"
      onClick={() => openLeadModal({ variant, courseSlug })}
      className={`${base} ${className}`}
    >
      {children}
      {!ghost && (
        // Button-in-button: the arrow lives in its own island and nudges on hover.
        <span
          aria-hidden
          className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm transition-transform duration-300 group-hover:translate-x-0.5 group-hover:scale-105"
        >
          →
        </span>
      )}
    </button>
  );

  // The one blue CTA gets the magnetic pull; the ghost stays quiet.
  return ghost ? button : <Magnetic>{button}</Magnetic>;
}
