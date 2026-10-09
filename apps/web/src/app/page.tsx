import type { Metadata } from "next";
import { JsonLd } from "@/components/landing/JsonLd";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { HomePage } from "@/components/argus/home/HomePage";
import { canonical, OG_IMAGES } from "@/lib/seo";

const TITLE = "Free AI Interview — 75% Clear Puts You in Front of HR | BrowseJobs";
const DESCRIPTION =
  "Free AI interview: about 15 questions from your CV. Score 75% or more and we send your CV to 3,000 HR recruiters. Below that, free help and a retake.";

export const metadata: Metadata = {
  title: { absolute: TITLE },
  description: DESCRIPTION,
  alternates: { canonical: canonical("/") },
  openGraph: {
    images: OG_IMAGES,
    title: TITLE,
    description: DESCRIPTION,
    url: canonical("/"),
  },
  twitter: {
    title: TITLE,
    description: DESCRIPTION,
  },
};

export default function Home() {
  return (
    <>
      <JsonLd />
      <ArgusFrame className="argus-home">
        <HomePage />
      </ArgusFrame>
    </>
  );
}
