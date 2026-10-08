import type { Metadata } from "next";
import { EnquiryForm } from "@/components/apple/EnquiryForm";
import { AppleShell } from "@/components/apple/AppleShell";
import { liveCourseSlug } from "@/content/enquiries";
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
};

export default async function CourseEnquirePage({
  searchParams,
}: {
  searchParams: Promise<{ course?: string }>;
}) {
  const params = await searchParams;
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
      <AppleShell>
        <section className="px-5 pb-20 pt-16 md:pt-24">
          <div className="mx-auto max-w-[720px] text-center">
            <h1 className="apple-display text-[clamp(2.75rem,6vw,5rem)]">Ask about a course.</h1>
            <p className="mx-auto mt-4 max-w-[36rem] text-[19px] leading-snug text-[#424245]">
              Tell us which course, and a time to call. Three free steps come before any fee.
            </p>
          </div>
          <div className="mt-12">
            <EnquiryForm type="course" course={liveCourseSlug(params.course)} />
          </div>
        </section>
      </AppleShell>
    </>
  );
}
