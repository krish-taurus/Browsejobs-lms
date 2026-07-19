import type { Metadata } from "next";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { BriefView } from "@/components/brief/BriefView";

export const metadata: Metadata = {
  title: "Daily Market Brief — Who's Hiring, Who's Cutting",
  description:
    "The daily India tech hiring brief: hiring announcements, reported layoffs and funding-to-hiring signals, every item sourced from public news.",
};

/** /brief — landing page for the daily email/WhatsApp teaser. */
export default function BriefPage() {
  return (
    <MarketingShell>
      <BriefView />
    </MarketingShell>
  );
}
