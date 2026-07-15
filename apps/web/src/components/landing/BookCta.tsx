"use client";

import type { ReactNode } from "react";
import {
  openLeadModal,
  type LeadVariant,
} from "@/components/landing/leadModalBus";

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
    : "rounded-full bg-trust px-6 py-3 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-all hover:-translate-y-0.5 hover:bg-deep";

  return (
    <button
      type="button"
      onClick={() => openLeadModal({ variant, courseSlug })}
      className={`${base} ${className}`}
    >
      {children}
    </button>
  );
}
