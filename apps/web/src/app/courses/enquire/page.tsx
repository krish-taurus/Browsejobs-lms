import type { Metadata } from "next";
import { Suspense } from "react";
import { CourseEnquiry } from "@/components/apple/EnquiryForm";
import { EnquiryStage } from "@/components/argus/EnquiryStage";
import { breadcrumbNode, canonical, jsonLdGraph, webPageNode } from "@/lib/seo";

const TITLE = "Ask about a course";
const DESCRIPTION =
  "Ask BrowseJobs about a live course. Share your number and a time to call. The course is a free conversation first — hiring is never promised.";
const PATH = "/courses/enquire";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
  twitter: { card: "summary_large_image", title: TITLE, description: DESCRIPTION, images: ["/og.png"] },
};

export default function CourseEnquirePage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Enquire", path: PATH },
    ]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <EnquiryStage
        title="Ask about a course."
        lede="Tell us which course, and a time to call. Three free steps come before any fee."
      >
        <Suspense fallback={<div className="min-h-[36rem]" />}>
          <CourseEnquiry />
        </Suspense>
      </EnquiryStage>
    </>
  );
}
