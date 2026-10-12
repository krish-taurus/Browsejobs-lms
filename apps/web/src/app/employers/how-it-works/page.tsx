import type { Metadata } from "next";
import { EmployersHowItWorksAp } from "@/components/ap/pages/EmployersHowItWorksAp";
import { breadcrumbNode, canonical, jsonLdGraph, webPageNode, OG_IMAGES } from "@/lib/seo";

const TITLE = "How the AI Recruiter works";
const DESCRIPTION =
  "The four bots, the old path and the new one, a labelled sample report, and every hiring stage. We call and screen, run pre-BGV, and prepare the offer. A person always releases the offer.";
const PATH = "/employers/how-it-works";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: {
    images: OG_IMAGES, title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
};

export default function EmployerHowPage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Employers", path: "/employers" },
      { name: "How it works", path: PATH },
    ]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <EmployersHowItWorksAp />
    </>
  );
}
