import type { Metadata } from "next";
import { ApShell } from "@/components/ap/ApShell";
import { ApJourneyFilm } from "@/components/ap/ApFilm";
import {
  SBelow75,
  SCounselling,
  SCourses,
  SDays,
  SFaq,
  SJourney,
  SOverview,
  SRecruiter,
  SSection11,
  SStories,
  STop,
  SWhat75,
} from "@/components/ap/generated/home";
import homeLd from "@/components/ap/ld/home.json";
import { courses } from "@/content/landing";
import { canonical, courseNode, OG_IMAGES } from "@/lib/seo";

const TITLE = "Free AI Interview — 75% Clear Puts You in Front of HR | BrowseJobs";
const DESCRIPTION =
  "Free AI interview: about 15 questions from your CV. Score 75% or more and we send your CV to 3,000 HR recruiters. Below that, free help and a retake.";

export const metadata: Metadata = {
  title: { absolute: TITLE },
  description: DESCRIPTION,
  alternates: { canonical: canonical("/") },
  openGraph: { images: OG_IMAGES, title: TITLE, description: DESCRIPTION, url: canonical("/") },
  twitter: { title: TITLE, description: DESCRIPTION },
};

/** Home, in the approved Apple-direction design (Oct 2026). */
export default function Home() {
  // Organization, WebSite and the founder come from the root layout; this page adds courses, its FAQ and the HowTo.
  const courseNodes = courses.flatMap((course) => {
    const node = course.live ? courseNode(course.slug) : null;
    return node ? [node] : [];
  });
  const ld = { ...homeLd, "@graph": [...courseNodes, ...homeLd["@graph"]] };
  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />
      <ApShell current="home">
        <STop />
        <SJourney />
        <ApJourneyFilm />
        <SWhat75 />
        <SBelow75 />
        <SCourses />
        <SCounselling />
        <SStories />
        <SRecruiter />
        <SDays />
        <SOverview />
        <SFaq />
        <SSection11 />
      </ApShell>
    </>
  );
}
