"use client";

import type { ReactNode } from "react";
import { openLeadModal, type LeadVariant } from "@/components/landing/leadModalBus";

/**
 * An Apple-direction pill that opens the site's single LeadModal (same
 * behaviour as components/landing/BookCta). The page must mount <LeadModal />
 * (MarketingShell does). `kind="more"` renders the text link with a chevron.
 */
export function ApLeadButton({
  children = "Book Free Masterclass",
  variant = "masterclass",
  courseSlug,
  kind = "primary",
}: {
  children?: ReactNode;
  variant?: LeadVariant;
  courseSlug?: string;
  kind?: "primary" | "secondary" | "more";
}) {
  const open = () => openLeadModal({ variant, courseSlug });
  if (kind === "more") {
    return (
      <button type="button" className="more ap-more-button" onClick={open}>
        {children} <span className="chev" aria-hidden="true">›</span>
      </button>
    );
  }
  return (
    <button type="button" className={`btn ${kind === "secondary" ? "btn-secondary" : "btn-primary"}`} onClick={open}>
      {children}
    </button>
  );
}
