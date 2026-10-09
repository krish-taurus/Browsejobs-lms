import type { Metadata } from "next";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { ArgusButton, ArgusMore } from "@/components/argus/ui";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { BotGrid } from "@/components/employers/BotGrid";
import { HiringJourney } from "@/components/employers/HiringJourney";
import { SampleReport } from "@/components/employers/SampleReport";
import { HomeStages } from "@/components/home/HomeStages";
import { breadcrumbNode, canonical, jsonLdGraph, webPageNode, OG_IMAGES } from "@/lib/seo";

const TITLE = "How the AI Recruiter works";
const DESCRIPTION =
  "The four bots, the old path and the new one, a labelled sample report, and every hiring stage. We call and screen, run pre-BGV, and prepare the offer. A person always releases the offer.";
const PATH = "/employers/how-it-works";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: {
    images: OG_IMAGES, title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
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
      <ArgusFrame className="argus-how">
        <section className="argus-section argus-how-hero">
          <div className="argus-how-copy">
            <h1 className="argus-h1">We reverse-engineered hiring.</h1>
            <p className="argus-body argus-hero-sub">
              From a message to about 3 days. We call and screen, run the rounds and pre-BGV, and prepare the offer. A person always releases it.
            </p>
            <div className="argus-row argus-start">
              <ArgusButton href="/employers/enquire">Get started</ArgusButton>
              <ArgusMore href="/employers/mission-control-demo">Watch the demo</ArgusMore>
            </div>
          </div>
        </section>

        <section id="bots" className="argus-section argus-how-block">
          <div className="argus-how-copy">
            <h2 className="argus-h2">A message. Then the bots.</h2>
            <p className="argus-body">Each bot has one job. You do not chase people by hand.</p>
            <BotGrid />
          </div>
        </section>

        <section id="journey" className="argus-section argus-how-block">
          <div className="argus-how-copy">
            <h2 className="argus-h2">From the message to day 3.</h2>
            <p className="argus-body">The old way is 90 days of manual calls. This path is about 3 days.</p>
            <Disclaimer tone="argus" />
            <HiringJourney />
          </div>
        </section>

        <section id="report" className="argus-section argus-how-block">
          <div className="argus-how-copy">
            <p className="argus-kicker">Sample report: example candidate</p>
            <h2 className="argus-h2">What you receive.</h2>
            <p className="argus-body">A performance report and the CV. The person below is an example. Not a real person.</p>
            <SampleReport />
          </div>
        </section>

        <div className="argus-stages">
          <HomeStages />
        </div>
      </ArgusFrame>
    </>
  );
}
