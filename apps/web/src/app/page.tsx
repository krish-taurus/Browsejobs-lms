import type { Metadata } from "next";
import { JsonLd } from "@/components/landing/JsonLd";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { HomeHero } from "@/components/home/HomeHero";
import { HowItWorks } from "@/components/home/HowItWorks";
import { EmployerBand } from "@/components/home/EmployerBand";
import { GapFork } from "@/components/home/GapFork";
import { CourseStrip } from "@/components/home/CourseStrip";
import { ProofAndPay } from "@/components/home/ProofAndPay";
import { HomeClose } from "@/components/home/HomeClose";
import { RecruiterStage } from "@/components/home/RecruiterStage";
import { homeNav } from "@/content/home";
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

/**
 * Home — night job-first funnel. The document title, description and
 * canonical stay the production homepage metadata. /v3 keeps the keynote.
 */
export default function Home() {
  return (
    <>
      <JsonLd />
      <MarketingShell links={homeNav} ctaLabel="Take your free AI interview" ctaHref="#interview-start" tone="night">
        <HomeHero />
        <HowItWorks />
        <EmployerBand />
        <RecruiterStage />
        <GapFork />
        <CourseStrip />
        <ProofAndPay />
        <HomeClose />
      </MarketingShell>
    </>
  );
}
