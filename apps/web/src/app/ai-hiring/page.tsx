import type { Metadata } from "next";
import {
  EmployerModels,
  EmployerPipeline,
  EmployerPricing,
  EmployerRoadmap,
  EmployerScenarios,
} from "@/components/seo/EmployerProduct";
import {
  ContactStrip,
  Contents,
  MoneyArticle,
  MoneyFaq,
  MoneyHero,
  MoneySection,
  RelatedLinks,
  TextLink,
} from "@/components/seo/MoneyArticle";
import { contact } from "@/content/landing";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.aiHiring;

const faqs = [
  {
    q: "What does the AI hiring platform actually do?",
    a: "You paste a job description, or start from a title and notes. The AI drafts a structured role — skills, experience band, locations, knockout questions — and you publish it. Applicants are ranked with a written reason. An AI caller screens the shortlist and files a transcript and a recording. L1 and L2, or rounds you design, run async and proctored. Your team gets a graded report before anyone meets the candidate. Offer release is a human action. It is never automated.",
  },
  {
    q: "Is this a replacement for our recruiters?",
    a: "No. It takes the first-pass work — structuring the JD, ranking, the screening call, and the async rounds — off the hours your team currently spends on the inbox. Your recruiters and hiring managers still set the bar, read the evidence, and decide. Automation can park someone for review. It cannot terminally reject them, and it cannot release an offer.",
  },
  {
    q: "Can we hire people who have already been interviewed?",
    a: "Two ways. You can run your own applicants through the pipeline, so the people you meet have already done the screen and the rounds. Or you can run it as an agency engagement on the BrowseJobs graded pool, and receive a graded report per finalist. “Pre-interviewed” means that file exists. It does not mean the person will accept, or that they will pass your bar.",
  },
  {
    q: "How much does AI hiring cost?",
    a: "The first six months are free: no card and no lock-in, for the pipeline from JD to handover brief. After that, two models are published. Agency is 8% of the candidate’s annual CTC per successful hire, confirmed in writing before the engagement. Per-interview pricing is discussed on the onboarding call and is not printed as a rate on the site. Nothing is charged without a written agreement.",
  },
  {
    q: "Do you publish a time-to-hire or a better-than-ATS statistic?",
    a: "No. There is no competitor comparison and no hire-rate claim on this page. Volumes, timelines, and outcomes depend on your role, your market, and your selection bar. Scenarios on the page are illustrations of the process, marked as such.",
  },
  {
    q: "Do you run background verification?",
    a: "Not the full check. A Trust Score built from DigiLocker ID, PAN, education certificates, and EPFO employment history is on the roadmap and is not available. What you get today is interview evidence: video replay, transcript, per-rubric scores, and the proctoring record. A flag never auto-rejects anyone.",
  },
  {
    q: "Will this connect to the ATS we already pay for?",
    a: "Not yet. The pipeline runs in its own workspace. A public API, webhooks, and CSV or ATS import are on the roadmap. If connecting an existing ATS is a launch requirement, say so on the call. We would rather tell you the timing than imply the integration exists.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "AI hiring", path: page.path },
] as const;

export const metadata: Metadata = moneyMetadata(page);

export default function AiHiringPage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode(crumbs),
    {
      "@type": "Service",
      name: "BrowseJobs AI hiring",
      serviceType: "AI-assisted recruitment pipeline",
      url: "https://browsejobs.ai/ai-hiring",
      provider: {
        "@type": "Organization",
        name: "BrowseJobs",
        email: contact.email,
        telephone: contact.phone,
        url: "https://browsejobs.ai",
      },
      areaServed: "IN",
      description:
        "AI hiring pipeline for teams: JD structuring, ranked applicants, an AI screening call, proctored interview rounds, and a written brief. Offer release stays with a human. Free for six months.",
    },
    faqNode(faqs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="For hiring teams"
        title={page.title}
        crumbs={crumbs}
        primary={{ kind: "link", href: "/employers", label: "Talk to us about a role" }}
        secondary={{ href: "/ai-interview-platform", label: "How the interviews run" }}
        lede={
          <p>
            BrowseJobs AI hiring is a pipeline for teams, not a student course. You bring a role. The product
            structures the job description, ranks applicants with a written reason, places a screening call, runs L1
            and L2 or a round you design, and hands your HR team a graded brief before a human meeting. The first
            six months are free. We do not publish a hire rate, a time-to-fill, or a claim that this beats another
            ATS.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#pipeline", label: "What runs today" },
          { href: "#models", label: "Your applicants, or ours" },
          { href: "#scenarios", label: "How teams use it" },
          { href: "#price", label: "What it costs" },
          { href: "#roadmap", label: "What is not built" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="pipeline" kicker="The product" heading="Automated screening, then a human decision">
        <p>
          The inbox problem is ordinary. A role attracts more CVs than a recruiter can read well, the first calls
          repeat the same five questions, and the hiring manager meets people before anyone has evidence. The
          pipeline is the work between “JD posted” and “panel’s first meeting”. It is not a decision to hire.
        </p>
        <p>
          Every stage below is copied from the employer product that ships at <TextLink href="/employers">/employers</TextLink>.
          If a capability is missing from this list, it is not something you can buy today. The interview-specific
          page — what the candidate hears, how rounds are proctored, what the brief contains — is{" "}
          <TextLink href="/ai-interview-platform">the AI interview platform</TextLink>.
        </p>
        <EmployerPipeline />
      </MoneySection>

      <MoneySection id="models" kicker="Two ways in" heading="Run your own applicants, or take finalists from the pool">
        <p>
          “Hire pre-interviewed talent” is the agency shape of the same pipeline. We operate the JD, the shortlist,
          the screening, and the rounds on the BrowseJobs graded candidate pool, and you receive the graded report
          per finalist. You still decide. A report is not an acceptance, and a candidate who has completed an AI
          round has not been hired.
        </p>
        <p>
          The other shape is your own inbound. You publish the JD in the workspace, point applicants at it, and they
          arrive graded against the bar you set. Your team keeps the decisions. A hiring CRM can be added later,
          scoped and priced separately. It is not required to start.
        </p>
        <EmployerModels />
      </MoneySection>

      <MoneySection id="scenarios" kicker="Illustrations" heading="Volume, a senior hire, or no recruiter at all">
        <p>
          These are process sketches from the employer page: a services firm with many seats, a product company
          hiring one senior person, a startup with no recruiter. They describe who does which step. They are not
          measured time savings, and they are not a promise about your req.
        </p>
        <EmployerScenarios />
      </MoneySection>

      <MoneySection id="price" kicker="Commercials" heading="Free for six months. Then a written model.">
        <p>
          Onboard, connect roles, and run the pipeline at no cost for six months. No card. No lock-in. Before that
          period ends we meet and agree what happens next. Agency is 8% of annual CTC per successful hire. The
          per-interview alternative, including the AI caller, is priced against your volume on that call — the site
          does not print a rupee figure for it, so this page will not invent one. Interview-only hiring is available
          on that second model. Nothing is charged without a written agreement.
        </p>
        <EmployerPricing />
      </MoneySection>

      <MoneySection id="roadmap" kicker="Not yet" heading="Say this out loud before a vendor review">
        <p>
          A hiring team evaluating software should know the absences. Full background verification, a public API,
          ATS import, and semantic candidate search are written down as not built. Treating the roadmap as the
          product is how evaluations go wrong. If one of these is a condition of signing, raise it on the call and
          expect a direct answer about timing.
        </p>
        <EmployerRoadmap />
      </MoneySection>

      <MoneyFaq
        faqs={faqs}
        intro={
          <p>
            Employer questions only. If you are a candidate looking for a course, this is the wrong page — start at{" "}
            <TextLink href="/courses">programmes</TextLink>.
          </p>
        }
      />
      <RelatedLinks
        links={[
          { href: "/employers", label: "Employers", note: "The product page, with the pipeline and the enquiry form." },
          { href: "/ai-interview-platform", label: "AI interview platform", note: "Screening calls, proctored rounds, and the brief." },
          { href: "/employer", label: "Employer sign in", note: "Workspace for teams already onboarded." },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
