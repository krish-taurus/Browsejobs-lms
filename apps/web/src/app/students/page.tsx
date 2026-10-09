import type { Metadata } from "next";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { StudentsPage as StudentsView } from "@/components/argus/home/StudentsPage";
import { recruiterFaqs } from "@/content/home";
import { breadcrumbNode, canonical, faqNode, jsonLdGraph, webPageNode, OG_IMAGES } from "@/lib/seo";

const TITLE = "Students — free AI interview";
const DESCRIPTION =
  "For job seekers: a free AI interview built from your CV and scored out of 100. Score 75% and your CV goes to HR; below that, free counselling first.";
const PATH = "/students";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: {
    images: OG_IMAGES, title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
  twitter: { title: TITLE, description: DESCRIPTION },
};

export default function StudentsPage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Students", path: PATH },
    ]),
    faqNode([...recruiterFaqs]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <ArgusFrame className="argus-home">
        <StudentsView />
      </ArgusFrame>
    </>
  );
}
