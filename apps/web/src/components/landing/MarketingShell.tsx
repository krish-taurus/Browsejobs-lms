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
 *
 * `variant` covers the one page that is not talking to students. The employer
 * page runs on the dark surfaces its portal uses, and its sticky CTA must not
 * offer a hiring manager a free masterclass — so the variant swaps the nav's
 * skin and the CTA's destination rather than having that page bypass the shell.
 */
export function MarketingShell({
  children,
  variant = "default",
}: {
  children: ReactNode;
  variant?: "default" | "employer";
}) {
  const employer = variant === "employer";

  return (
    <div className={employer ? "bg-deck" : undefined}>
      <SmoothScroll />
      <ScrollProgress />
      <Nav tone={employer ? "dark" : "light"} />
      <main>{children}</main>
      <Footer />
      <LeadModal />
      <StickyCta variant={variant} />
    </div>
  );
}
