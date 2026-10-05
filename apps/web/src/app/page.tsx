import type { Metadata } from "next";
import { JsonLd } from "@/components/landing/JsonLd";
import { canonical } from "@/lib/seo";
import V3Landing from "./v3/page";

const TITLE = "Data Engineering & AI Courses in Bengaluru — Pay After You're Hired | BrowseJobs";
const DESCRIPTION =
  "Data Engineering and AI courses from Bengaluru. Three free steps before you pay. The placement fee is due only after you accept an offer. Built from real interviews.";

export const metadata: Metadata = {
  title: { absolute: TITLE },
  description: DESCRIPTION,
  alternates: { canonical: canonical("/") },
  openGraph: {
    title: TITLE,
    description: DESCRIPTION,
    url: canonical("/"),
  },
  twitter: {
    title: TITLE,
    description: DESCRIPTION,
  },
};

/**
 * Home — the keynote landing (v3 template, promoted to live): hero → dashboard →
 * manifesto → path → AI scenes → bento → programs → career report → fees →
 * verify → stories (LLM question bank) → reviews → market signals → CTA.
 * /v3 remains as a preview alias of the same component. The previous section
 * components (MarketPulse, IntelBoard, SalaryExplorer, …) remain available
 * under components/landing for reuse inside the template.
 */
export default function Home() {
  return (
    <>
      <JsonLd />
      <V3Landing />
    </>
  );
}
