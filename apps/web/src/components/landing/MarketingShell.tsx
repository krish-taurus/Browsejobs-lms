import type { ReactNode } from "react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { LeadModal } from "@/components/landing/LeadModal";
import { StickyCta } from "@/components/landing/StickyCta";
import { SmoothScroll } from "@/components/motion/SmoothScroll";
import { ScrollProgress } from "@/components/motion/ScrollProgress";

/**
 * Shared chrome for every public/marketing page (one system, spec §5): sticky nav,
 * footer, the single lead modal, the mobile sticky CTA, and Lenis smooth scroll. Pages
 * render only their own content inside — nothing re-imports chrome ad hoc.
 */
export function MarketingShell({ children }: { children: ReactNode }) {
  return (
    <>
      <SmoothScroll />
      <ScrollProgress />
      <Nav />
      <main>{children}</main>
      <Footer />
      <LeadModal />
      <StickyCta />
    </>
  );
}
