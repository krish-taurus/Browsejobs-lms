import type { Metadata } from "next";
import { JsonLd } from "@/components/landing/JsonLd";
import { AppleHome } from "@/components/apple/AppleHome";
import { AppleShell } from "@/components/apple/AppleShell";
import { canonical } from "@/lib/seo";

const TITLE = "Free AI Interview — 75% Clear Puts You in Front of HR | BrowseJobs";
const DESCRIPTION =
  "Take a free AI interview. About 15 questions from your CV. Score 75% or more and your CV is sent to 3,000 HR recruiters. Clearing raises your chance of an interview call by almost 60%. Employers hire in about 3 days, down from 90.";

export const metadata: Metadata = {
  title: { absolute: TITLE },
  description: DESCRIPTION,
  alternates: { canonical: canonical("/") },
  openGraph: {
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
      <AppleShell>
        <AppleHome />
      </AppleShell>
    </>
  );
}
