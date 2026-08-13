import { JsonLd } from "@/components/landing/JsonLd";
import { SITE_DESCRIPTION, SITE_TITLE, pageMetadata } from "@/lib/seo";
import V3Landing from "./v3/page";

// The brand title already names BrowseJobs, so it skips the "· BrowseJobs" template.
export const metadata = pageMetadata({
  title: SITE_TITLE,
  description: SITE_DESCRIPTION,
  path: "/",
  absoluteTitle: true,
});

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
