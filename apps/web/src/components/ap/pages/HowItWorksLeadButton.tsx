"use client";

import type { ReactNode } from "react";
import { openLeadModal, type LeadVariant } from "@/components/landing/leadModalBus";

/** An Apple-style pill that opens the shared LeadModal (counselling or masterclass). */
export function HowItWorksLeadButton({
  variant,
  className = "btn btn-primary",
  children,
}: {
  variant: LeadVariant;
  className?: string;
  children: ReactNode;
}) {
  return (
    <button type="button" className={className} onClick={() => openLeadModal({ variant })}>
      {children}
    </button>
  );
}
