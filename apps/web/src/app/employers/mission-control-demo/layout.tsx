import type { Metadata } from "next";
import type { ReactNode } from "react";
import { absoluteUrl } from "@/lib/seo";

const TITLE = "Mission control demo";
const DESCRIPTION = "A scripted preview of the employer hiring desk. Demo data only. Not a live workspace.";
const PATH = "/employers/mission-control-demo";

/** Scripted look-and-feel preview. Not a public page and not a live desk. */
export const metadata: Metadata = {
  title: TITLE,
  description: DESCRIPTION,
  robots: { index: false, follow: false, nocache: true },
  alternates: { canonical: absoluteUrl(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: absoluteUrl(PATH) },
  twitter: { title: TITLE, description: DESCRIPTION },
};

export default function MissionControlDemoLayout({ children }: { children: ReactNode }) {
  return <div data-theme="dark" className="min-h-screen bg-paper text-fg">{children}</div>;
}
