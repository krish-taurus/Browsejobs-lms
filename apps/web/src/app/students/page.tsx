import type { Metadata } from "next";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { StudentsPage as StudentsView } from "@/components/argus/home/StudentsPage";
import { recruiterFaqs } from "@/content/home";
import { breadcrumbNode, canonical, faqNode, jsonLdGraph, webPageNode } from "@/lib/seo";

const TITLE = "Students — free AI interview";
const DESCRIPTION =
  "Take a free AI interview. About 15 questions from your CV. Score 75% or more and your CV is sent to 3,000 HR recruiters. Clearing raises your chance of an interview call by almost 60%. Below 75%, book a free counselling session.";
const PATH = "/students";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
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
