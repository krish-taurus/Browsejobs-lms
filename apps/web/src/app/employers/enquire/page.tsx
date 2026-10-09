import type { Metadata } from "next";
import { Suspense } from "react";
import { EmployerEnquiry } from "@/components/apple/EnquiryForm";
import { EnquiryStage } from "@/components/argus/EnquiryStage";
import { breadcrumbNode, canonical, jsonLdGraph, webPageNode } from "@/lib/seo";

const TITLE = "Enquire to hire";
const DESCRIPTION =
  "Tell BrowseJobs the role, the city, and when you want to hire. We call you back. Nobody can guarantee a hire — the market decides.";
const PATH = "/employers/enquire";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
  twitter: { card: "summary_large_image", title: TITLE, description: DESCRIPTION, images: ["/og.png"] },
};

export default function EmployerEnquirePage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Employers", path: "/employers" },
      { name: "Enquire", path: PATH },
    ]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <EnquiryStage
        title="Enquire to hire."
        lede="Share the role and when you want to start. We call you back. Hiring still depends on the market."
      >
        <Suspense fallback={<div className="min-h-[36rem]" />}>
          <EmployerEnquiry />
        </Suspense>
      </EnquiryStage>
    </>
  );
}
