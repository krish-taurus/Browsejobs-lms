import type { Metadata } from "next";
import type { ReactNode } from "react";
import { absoluteUrl, OG_IMAGES } from "@/lib/seo";

const TITLE = "BrowseJobs AI Recruiter — demo";
const DESCRIPTION =
  "Watch the BrowseJobs AI Recruiter, powered by Taurus, fill a role through WhatsApp: screening, AI calls, L1 and L2, optional pre-BGV and an offer you approve. Sample data.";
const PATH = "/employers/mission-control-demo";

/** The Taurus hiring story, full screen. Sample data; not a live hiring desk. */
export const metadata: Metadata = {
  title: { absolute: TITLE },
  description: DESCRIPTION,
  robots: { index: false, follow: false, nocache: true },
  alternates: { canonical: absoluteUrl(PATH) },
  openGraph: { images: OG_IMAGES, title: TITLE, description: DESCRIPTION, url: absoluteUrl(PATH) },
  twitter: { title: TITLE, description: DESCRIPTION },
};

export default function MissionControlDemoLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
