import type { Metadata } from "next";
import { JsonLd } from "@/components/landing/JsonLd";
import { AppleHome } from "@/components/apple/AppleHome";
import { AppleShell } from "@/components/apple/AppleShell";
import { canonical } from "@/lib/seo";

const TITLE = "Free AI Interview — 75% Clear Puts You in Front of HR | BrowseJobs";
const DESCRIPTION =
  "Take a free AI interview. Fifteen questions from your CV, scored out of 100. A score of 75% or more counts as clear and puts you in front of HR with your score. Employers hire with the BrowseJobs AI Recruiter and watch each stage. Calls, background checks, and offers are not live yet.";

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
