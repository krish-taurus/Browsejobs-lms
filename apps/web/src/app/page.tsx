import type { Metadata } from "next";
import { JsonLd } from "@/components/landing/JsonLd";
import { getSiteContent, seoFor } from "@/lib/site-content";
import V3Landing from "./v3/landing";

/**
 * Home — the keynote landing (v3 template, promoted to live). Hero copy and
 * SEO come from the CRM-managed site content (Website Content page) with the
 * shipped copy as fallback; /v3 remains a preview alias of the same component.
 */

const FALLBACK_SEO = {
  title: "BrowseJobs — Built from real interviews.",
  description:
    "This syllabus was not written. It was reverse-engineered — from up to ~50 real & mock interviews monitored daily. Three free steps before you pay anything; placement fee only after you accept an offer.",
};

export async function generateMetadata(): Promise<Metadata> {
  const content = await getSiteContent();
  const seo = seoFor(content, "/", FALLBACK_SEO);

  return {
    title: seo.title,
    description: seo.description,
    openGraph: { title: seo.title, description: seo.description },
  };
}

export default async function Home() {
  const content = await getSiteContent();

  return (
    <>
      <JsonLd />
      <V3Landing hero={content.hero} copy={content.homepage as Record<string, string> | undefined} />
    </>
  );
}
