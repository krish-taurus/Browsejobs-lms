import type { Metadata } from "next";
import { AppleShell } from "@/components/apple/AppleShell";
import { LeadModal } from "@/components/landing/LeadModal";
import { CourseStrip } from "@/components/home/CourseStrip";
import { GapFork } from "@/components/home/GapFork";
import { HomeClose } from "@/components/home/HomeClose";
import { HowItWorks } from "@/components/home/HowItWorks";
import { ProofAndPay } from "@/components/home/ProofAndPay";
import { faqs } from "@/content/landing";
import { recruiterFaqs } from "@/content/home";
import { breadcrumbNode, canonical, faqNode, jsonLdGraph, webPageNode } from "@/lib/seo";

const TITLE = "How the free AI interview works";
const DESCRIPTION =
  "Sit a free AI interview, get a score, and see what happens at 75%. Counselling is free if you miss. A course comes only if you need it. The 500% pickup line is historical BrowseJobs data, not a promise.";
const PATH = "/how-it-works";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
};

export default function HowItWorksPage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "How it works", path: PATH },
    ]),
    faqNode([...recruiterFaqs, ...faqs]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <AppleShell>
        <section className="px-5 pb-8 pt-16 text-center md:pt-24">
          <h1 className="apple-display mx-auto max-w-[12ch] text-[clamp(2.75rem,8vw,5.5rem)]">
            Interview.{" "}
            <br />
            Score.{" "}
            <br />
            Get seen.
          </h1>
          <p className="mx-auto mt-5 max-w-[36rem] text-[19px] leading-snug md:text-[21px]">
            The free AI interview is the front door. Everything else on this page is what happens after the score.
          </p>
        </section>
        <div className="home-canvas !min-h-0">
          <HowItWorks />
          <GapFork />
          <CourseStrip />
          <ProofAndPay />
          <HomeClose />
        </div>
        <LeadModal />
      </AppleShell>
    </>
  );
}
