import type { Metadata } from "next";
import { HowItWorksAp } from "@/components/ap/pages/HowItWorksAp";
import { faqs } from "@/content/landing";
import { recruiterFaqs } from "@/content/home";
import { breadcrumbNode, canonical, faqNode, jsonLdGraph, webPageNode, OG_IMAGES } from "@/lib/seo";

const TITLE = "How the free AI interview works";
const DESCRIPTION =
  "How the free AI interview works: answer about 15 questions, get a score out of 100, and see what happens at 75%, and what happens if you miss.";
const PATH = "/how-it-works";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: {
    images: OG_IMAGES, title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
};

export default function HowItWorksPage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "How it works", path: PATH },
    ]),
    faqNode([...recruiterFaqs, ...faqs]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <HowItWorksAp />
    </>
  );
}
