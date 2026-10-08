import type { Metadata } from "next";
import type { ReactNode } from "react";
import { AppleNav } from "@/components/apple/AppleNav";
import "@/components/apple/apple.css";
import { absoluteUrl } from "@/lib/seo";

const TITLE = "BrowseJobs AI Recruiter — demo";
const DESCRIPTION =
  "A scripted preview of the BrowseJobs AI Recruiter. Sample data. We call and screen, run pre-BGV, and prepare the offer. A person always releases the offer.";
const PATH = "/employers/mission-control-demo";

/** Scripted look-and-feel preview. Not a public listing and not a live desk. */
export const metadata: Metadata = {
  title: { absolute: TITLE },
  description: DESCRIPTION,
  robots: { index: false, follow: false, nocache: true },
  alternates: { canonical: absoluteUrl(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: absoluteUrl(PATH) },
  twitter: { title: TITLE, description: DESCRIPTION },
};

export default function MissionControlDemoLayout({ children }: { children: ReactNode }) {
  return (
    <div data-theme="dark" className="demo-frame min-h-screen bg-[#01040a] text-white">
      <AppleNav dark />
      {children}
    </div>
  );
}
