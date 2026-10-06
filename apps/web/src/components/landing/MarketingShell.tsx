import type { ReactNode } from "react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { LeadModal } from "@/components/landing/LeadModal";
import { StickyCta } from "@/components/landing/StickyCta";
import { SmoothScroll } from "@/components/motion/SmoothScroll";
import { ScrollProgress } from "@/components/motion/ScrollProgress";
import type { LeadVariant } from "@/components/landing/leadModalBus";

/**
 * Shared chrome for every public/marketing page (one system, spec §5): sticky nav,
 * footer, the single lead modal, the mobile sticky CTA, and Lenis smooth scroll. Pages
 * render only their own content inside — nothing re-imports chrome ad hoc.
 */
export function MarketingShell({
  children,
  links,
  ctaLabel,
  ctaVariant,
  ctaHref,
  tone = "light",
}: {
  children: ReactNode;
  links?: readonly { href: string; label: string }[];
  ctaLabel?: string;
  ctaVariant?: LeadVariant;
  /** When set, the nav and sticky bar link here instead of opening the lead modal. */
  ctaHref?: string;
  tone?: "light" | "night";
}) {
  return (
    <>
    <div className={tone === "night" ? "home-canvas" : undefined}>
      <SmoothScroll />
      <ScrollProgress />
      <Nav links={links} ctaLabel={ctaLabel} ctaVariant={ctaVariant} ctaHref={ctaHref} tone={tone} />
      <main>{children}</main>
      <Footer />
      <StickyCta label={ctaLabel} variant={ctaVariant} href={ctaHref} tone={tone} />
    </div>
    <LeadModal />
    </>
  );
}
