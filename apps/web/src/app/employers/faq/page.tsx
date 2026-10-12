import type { Metadata } from "next";
import { EmployersFaqAp } from "@/components/ap/pages/EmployersFaqAp";
import { EMPLOYER_FAQ } from "@/content/employer-landing";
import { breadcrumbNode, canonical, faqNode, jsonLdGraph, webPageNode, OG_IMAGES } from "@/lib/seo";

const TITLE = "Employer questions";
const DESCRIPTION =
  "Straight answers on the 3-day path, the 75% clear mark, the four bots, and what BrowseJobs will not promise. Nobody can guarantee a hire.";
const PATH = "/employers/faq";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: {
    images: OG_IMAGES, title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
};

export default function EmployerFaqPage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Employers", path: "/employers" },
      { name: "FAQ", path: PATH },
    ]),
    faqNode(EMPLOYER_FAQ),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <EmployersFaqAp />
    </>
  );
}
