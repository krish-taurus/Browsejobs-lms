import type { Metadata } from "next";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { AppleShell } from "@/components/apple/AppleShell";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { CareerCourses } from "@/components/apple/CareerCourses";
import { SuccessStories } from "@/components/apple/SuccessStories";
import { WhatsAppMessages } from "@/components/apple/WhatsAppMessages";
import { AfterYouClear, BelowSeventyFive, CounsellingBlock, HowTheInterviewWorks, StudentFaq, WhatSeventyFive } from "@/components/apple/StudentStory";
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
      <AppleShell>
        <section id="top" className="bg-white px-5 pb-16 pt-8 text-center md:pb-20 md:pt-10">
          <p className="text-[17px] font-medium text-[#0a7040]">Free</p>
          <h1 className="apple-display mx-auto mt-3 max-w-[14ch] text-[clamp(2.75rem,7vw,5.5rem)]">
            Take a free{" "}
            <br />
            AI interview.
          </h1>
          <p className="apple-sub mt-4 text-[#1d1d1f]">
            It&apos;s free. About 15 questions from your CV. Score 75% and your CV is sent to 3,000 HR recruiters.
          </p>
          <div className="apple-note mt-2">
            <Disclaimer />
          </div>
          <InterviewStartForm tone="apple" />
        </section>
        <WhatSeventyFive />
        <BelowSeventyFive />
        <CareerCourses />
        <CounsellingBlock />
        <HowTheInterviewWorks />
        <AfterYouClear />
        <SuccessStories fuller />
        <WhatsAppMessages variant="grid" />
        <StudentFaq />
      </AppleShell>
    </>
  );
}
