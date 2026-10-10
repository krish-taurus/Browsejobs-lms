import type { ReactNode } from "react";
import { ApShell, type ApCurrent } from "@/components/ap/ApShell";
import { LeadModal } from "@/components/landing/LeadModal";
import type { LeadVariant } from "@/components/landing/leadModalBus";

/**
 * Shared chrome for public/marketing pages — now the Apple-direction shell
 * (components/ap): one nav, one footer, one type system across the site.
 * The lead modal stays mounted for pages whose buttons open it (Book Free
 * Masterclass). Older props are accepted so callers don't change.
 */
export function MarketingShell({
  children,
  ctaLabel,
  ctaHref,
  current,
}: {
  children: ReactNode;
  links?: readonly { href: string; label: string }[];
  ctaLabel?: string;
  ctaVariant?: LeadVariant;
  /** When set, the nav button links here (otherwise: the free AI interview). */
  ctaHref?: string;
  tone?: "light" | "night";
  current?: ApCurrent;
}) {
  return (
    <>
      <ApShell current={current} cta={ctaLabel && ctaHref ? { label: ctaLabel, href: ctaHref } : undefined}>
        {children}
      </ApShell>
      <LeadModal />
    </>
  );
}
