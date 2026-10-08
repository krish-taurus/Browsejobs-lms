import type { Metadata } from "next";
import Link from "next/link";
import { AppleShell } from "@/components/apple/AppleShell";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { BotGrid } from "@/components/employers/BotGrid";
import { HiringJourney } from "@/components/employers/HiringJourney";
import { SampleReport } from "@/components/employers/SampleReport";
import { HomeStages } from "@/components/home/HomeStages";
import { breadcrumbNode, canonical, jsonLdGraph, webPageNode } from "@/lib/seo";

const TITLE = "How the AI Recruiter works";
const DESCRIPTION =
  "The four bots, the old path and the new one, a labelled sample report, and every hiring stage. Calls, background checks, offers, and joining are not live. A person always releases the offer.";
const PATH = "/employers/how-it-works";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
};

export default function EmployerHowPage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Employers", path: "/employers" },
      { name: "How it works", path: PATH },
    ]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <AppleShell>
        <section className="px-5 pb-4 pt-16 text-center md:pt-24">
          <h1 className="apple-display mx-auto max-w-[16ch] text-[clamp(2.15rem,6vw,4.75rem)]">We reverse-engineered hiring.</h1>
          <p className="mx-auto mt-5 max-w-[36rem] text-[19px] leading-snug md:text-[21px]">
            From a message to day 3. Calls, background checks, offers, and joining chats are not live yet.
          </p>
          <p className="mt-4 flex flex-wrap items-center justify-center gap-x-7">
            <Link href="/employers/enquire" className="apple-pill">
              Get started
            </Link>
            <Link href="/employers/mission-control-demo" className="apple-more">
              Watch the demo <span aria-hidden>›</span>
            </Link>
          </p>
        </section>

        <section id="bots" className="scroll-mt-28 border-t border-black/10 bg-white">
          <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
            <h2 className="apple-display max-w-3xl text-[clamp(2rem,4vw,3.5rem)] text-[#1d1d1f]">A message. Then the bots.</h2>
            <p className="mt-4 max-w-2xl text-[19px] leading-snug text-[#424245]">Each bot has one job. You do not chase people by hand.</p>
            <BotGrid />
          </div>
        </section>

        <section id="journey" className="scroll-mt-28 bg-[#f5f5f7]">
          <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
            <h2 className="apple-display max-w-3xl text-[clamp(2rem,4vw,3.5rem)]">From the message to day 3.</h2>
            <p className="mt-4 max-w-2xl text-[19px] leading-snug text-[#424245]">
              The old way is 90 days of manual calls. This path is about 3 days.
            </p>
            <Disclaimer className="mt-4 max-w-2xl" />
            <HiringJourney />
          </div>
        </section>

        <section id="report" className="scroll-mt-28 bg-white">
          <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
            <p className="text-[14px] font-semibold text-[#1b6df0]">Sample report: example candidate</p>
            <h2 className="apple-display mt-3 max-w-3xl text-[clamp(2rem,4vw,3.5rem)]">What you receive.</h2>
            <p className="mt-4 max-w-2xl text-[19px] leading-snug text-[#424245]">
              A performance report and the CV. The person below is an example. Not a real person.
            </p>
            <SampleReport />
          </div>
        </section>

        <div className="home-canvas !min-h-0">
          <HomeStages />
        </div>
      </AppleShell>
    </>
  );
}
