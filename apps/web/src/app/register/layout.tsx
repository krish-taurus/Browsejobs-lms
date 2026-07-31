import type { Metadata } from "next";
import { cmsMetadata } from "@/lib/site-content";

/**
 * /register is a client component, so its metadata lives here — this keeps the
 * page's title/description CRM-editable like every other public page
 * (Website → SEO).
 */
export async function generateMetadata(): Promise<Metadata> {
  return cmsMetadata("/register", {
    title: "Create your BrowseJobs account",
    description:
      "Start with the three free steps — counselling, masterclass, bootcamp. Placement fee only after you accept an offer.",
  });
}

export default function RegisterLayout({ children }: { children: React.ReactNode }) {
  return children;
}
