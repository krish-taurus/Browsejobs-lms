import type { Metadata } from "next";
import { EmployerPipeline, EmployerPricing } from "@/components/seo/EmployerProduct";
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
import { EMPLOYER_DISCLAIMER } from "@/content/employers";
import { contact } from "@/content/landing";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.aiInterview;

const faqs = [
  {
    q: "What is included in the AI interview platform?",
    a: "A screening call placed automatically, with the candidate told at the start that they are speaking to an AI. Then async, proctored rounds — L1, L2, or a round you design — with questions from your bank, generated, or blended. Then a written brief: scores, reasoning, the call transcript, and the proctoring record. Any round can be handed to a human panel with self-serve slot booking.",
  },
  {
    q: "Do candidates know the interviewer is an AI?",
    a: "Yes. Candidates are told at the start of the call. Every call is recorded and AI-monitored. The same standard is published on the student side of BrowseJobs. A surprise AI interview is not the product.",
  },
  {
    q: "Who writes the questions?",
    a: "You do, or the product generates them from the JD, or you blend the two. Custom rounds use your questions and your rubric. On publish, the JD’s own interview mock and grading rubric are generated. You can still replace them.",
  },
  {
    q: "Can the platform reject a candidate on its own?",
    a: "No. Rules can advance someone, unlock the next round, send a nudge, or park them for review. They cannot terminally reject, and they cannot release an offer. Offer release always takes an explicit human action from a permitted role. A proctoring flag surfaces evidence. It does not auto-reject.",
  },
  {
    q: "What happens if the candidate does not pick up?",
    a: "They return to the queue. They are not dropped. The call, when it happens, files a full transcript, a recording, an outcome, and a read on sentiment. The next step is then decided from that record, not from a missed ring.",
  },
  {
    q: "How do we start, and what does a round cost?",
    a: "The pipeline, including AI screening calls, is free for the first six months. After that, per-interview pricing is discussed in the onboarding meeting and is not printed here. Agency hiring, if you want us to run sourcing as well, is 8% of annual CTC per successful hire, agreed in writing first. Book that conversation from the employers page.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "AI hiring", path: "/ai-hiring" },
  { name: "AI interview platform", path: page.path },
] as const;

export const metadata: Metadata = moneyMetadata(page);

export default function AiInterviewPlatformPage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode(crumbs),
    {
      "@type": "Service",
      name: "BrowseJobs AI interview platform",
      serviceType: "AI screening calls and proctored interview rounds",
      url: "https://browsejobs.ai/ai-interview-platform",
      provider: {
        "@type": "Organization",
        name: "BrowseJobs",
        email: contact.email,
        telephone: contact.phone,
        url: "https://browsejobs.ai",
      },
      areaServed: "IN",
      description:
        "AI interviews for hiring teams: a disclosed screening call, proctored L1 and L2 or custom rounds, and a written brief. Humans release every offer.",
    },
    faqNode(faqs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="For hiring teams · interviews"
        title={page.title}
        crumbs={crumbs}
        primary={{ kind: "link", href: "/employers", label: "Book an employer call" }}
        secondary={{ href: "/ai-hiring", label: "The full hiring pipeline" }}
        lede={
          <p>
            The AI interview platform is the part of BrowseJobs hiring that speaks to candidates and scores the
            conversation. A caller screens the shortlist and says, at the start, that it is an AI. Later rounds run
            async and proctored, on your questions or on questions generated from the job description. Your panel
            reads the replay, the transcript, and the scores before it spends an hour. The platform does not hire
            anyone. A person releases the offer.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#call", label: "The screening call" },
          { href: "#rounds", label: "L1, L2, and custom rounds" },
          { href: "#brief", label: "The brief your team reads" },
          { href: "#humans", label: "What stays human" },
          { href: "#read", label: "How to read a brief" },
          { href: "#price", label: "Price" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="call" kicker="First conversation" heading="The screening call is placed for you, and labelled as AI">
        <p>
          An AI caller dials the shortlist and has the conversation a recruiter would usually have first: the basics
          you would otherwise chase across a week of phone tag. It files the transcript, the recording, the outcome,
          and a read on how the conversation went. If nobody picks up, the candidate goes back to the queue rather
          than into a reject pile. The call is multi-language, and every call is recorded and AI-monitored.
        </p>
        <p>
          Candidates are told at the start. That is a product rule, not a courtesy we hope the model remembers.
          Hiring teams sometimes ask whether disclosure will hurt show rate. We do not have a published number for
          that, and this page will not invent one. Disclosure is not optional in the product as it ships.
        </p>
        <EmployerPipeline ids={["call"]} />
      </MoneySection>

      <MoneySection id="rounds" kicker="The rounds" heading="L1, L2, or a round you design">
        <p>
          After the screen, rounds run async. The candidate is notified on WhatsApp or email and completes the round
          inside a window you set. L1 can unlock L2 the same day. The question source is per job description: your
          bank, AI-generated from the role, or a blend. A custom round can be a coding task or an assignment. The
          round is proctored the way the mocks are proctored: face-match, window-switch, and snapshot flags.
        </p>
        <p>
          Any round can be handed to a human panel, with the candidate booking a slot. Use that when the signal you
          need is a conversation your team must have themselves. Use the async round when you need a comparable
          recording before you spend the panel. The platform does not decide which of those you value. You do, per
          role.
        </p>
        <EmployerPipeline ids={["rounds"]} />
      </MoneySection>

      <MoneySection id="brief" kicker="Evidence" heading="A written brief before you meet anyone">
        <p>
          The output your team is meant to read is a graded report: every round, every score, every flag, the
          screening transcript, and the proctoring record in one view. Replay is seekable — click a line in the
          transcript and the video moves there. Scores are per rubric dimension, so you can see what carried the
          result instead of trusting a single mark. Hiring-manager comments and mentions stay inside your workspace.
        </p>
        <p>
          A flag is evidence for a human to weigh. It is not a silent rejection. That distinction is the difference
          between an interview platform and an automated no. If your policy is that a failed face-match ends the
          process, a person on your team still has to apply the policy. The software will not do it alone.
        </p>
        <EmployerPipeline ids={["bgv", "report"]} />
        <p className="mono text-[11px] leading-relaxed text-muted">{EMPLOYER_DISCLAIMER}</p>
      </MoneySection>

      <MoneySection id="humans" kicker="The boundary" heading="Automation stops before the offer">
        <p>
          Rules on the pipeline can advance a candidate, open the next round, nudge a stalled review, or park
          someone. You can simulate a rule against your last 30 days of applicants before you enable it. What a rule
          cannot do is reject terminally, or release an offer. Offer release is an explicit human action from a
          permitted role. That is the line to put in a security review, because it is the line the product actually
          draws.
        </p>
        <p>
          The interviews sit inside a longer hiring flow: JD structuring, ranking, the ATS board, and the two
          commercial models. That flow is{" "}
          <TextLink href="/ai-hiring">AI hiring</TextLink>, and the page with the enquiry form is{" "}
          <TextLink href="/employers">for employers</TextLink>. This page is only the interview.
        </p>
        <p>
          Full background verification — DigiLocker, PAN, education certificates, EPFO — is not part of the
          interview and is not live. Do not write it into a candidate communication as if the brief included it. The
          brief includes interview evidence. Say that.
        </p>
      </MoneySection>

      <MoneySection id="read" kicker="For the hiring manager" heading="How to read the brief without treating a score as a hire">
        <p>
          A useful brief answers four questions before you open a calendar. Did the candidate clear the knockout
          questions you set on the JD, or were those filters skipped? Which rubric dimension carried the score, and
          which one was thin? What did the screening call actually record — transcript and audio, not a one-line
          sentiment? What did proctoring flag, and is the snapshot evidence something a person should look at?
        </p>
        <p>
          The rank on the shortlist is explained in writing for the same reason. “Why this person is first” cites
          skills, mock evidence, and readiness signals. Ungraded applicants sit below that list, with an invite to
          the mock rather than a silent discard. If the written reason is vague, the round was vague. Fix the
          rubric before you blame the candidate or the model.
        </p>
        <p>
          None of this produces a time-to-hire. A panel that reads the brief and still runs its own hour will be
          slower than a panel that trusts a number. That is a legitimate choice. The product’s claim is only that
          the hour can start from evidence instead of from a CV. It is not a claim that your bar will be met more
          often, or that candidates will accept. Bring one open role if you want to see the brief on your own JD.
          The form for that conversation is on the employers page.
        </p>
      </MoneySection>

      <MoneySection id="price" kicker="Commercials" heading="Free while you prove it on a real role">
        <p>
          AI screening calls are included in the six free months. After that, you either continue on the agency
          model at 8% of CTC per successful hire, or on a per-interview price that is shared in the onboarding
          meeting rather than on this page. Inventing a per-interview rupee amount would be a fake rate. Bring one
          open role to the call if you want the number for your volume.
        </p>
        <EmployerPricing />
      </MoneySection>

      <MoneyFaq faqs={faqs} />
      <RelatedLinks
        links={[
          { href: "/employers", label: "Employers", note: "Enquiry form and the seven-stage pipeline." },
          { href: "/ai-hiring", label: "AI hiring", note: "JD, ranking, models, and what is not built yet." },
          { href: "/employer", label: "Employer sign in", note: "For a workspace that is already open." },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
