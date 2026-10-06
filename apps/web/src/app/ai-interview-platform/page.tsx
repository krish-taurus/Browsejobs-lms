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
    a: "An async AI interview for the role, spoken or typed, graded against that job's rubric. L1, L2, or a round you design. Questions come from the bank generated for the job. A human round is a conversation your team holds. It is tracked here, not generated, and there is no slot-booking network yet. The brief is the answers, the dimension scores once graded, and a short written read. Camera and window-switch checks are not captured. A score stays empty until grading finishes.",
  },
  {
    q: "Do candidates know the interviewer is an AI?",
    a: "The interview is an AI interview. We do not present it as a person on the phone. There is no outbound dialler. Counselling calls on the student side are recorded and AI-monitored. That is a different door from this interview.",
  },
  {
    q: "Who writes the questions?",
    a: "You do, or the product generates them from the JD, or you blend the two. Custom rounds use your questions and your rubric. On publish, the JD’s own interview mock and grading rubric are generated. You can still replace them.",
  },
  {
    q: "Can the platform reject a candidate on its own?",
    a: "No. Rules can advance someone or park them for review. They cannot terminally reject, and they cannot release an offer. Offer release always takes an explicit human action from a permitted role. Sending a round does not itself move the stage. Below the bar, nothing is sent and nobody is rejected.",
  },
  {
    q: "What happens if the candidate does not pick up?",
    a: "They stay in the queue. They are not rejected for an interview they have not finished. A score appears only after they answer and grading finishes. If grading is delayed, the interview says so. We do not fill in a number to keep the board looking complete.",
  },
  {
    q: "How do we start, and what does a round cost?",
    a: "The pipeline, including role-specific AI interviews, is free for the first six months. After that, per-interview pricing is discussed in the onboarding meeting and is not printed here. Agency hiring, if you want us to run sourcing as well, is 8% of annual CTC per successful hire, agreed in writing first. Book that conversation from the employers page.",
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
      serviceType: "Role-specific AI interviews for hiring teams",
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
        "AI interviews for hiring teams: role-specific questions, scores only after grading, and a written brief. Humans release every offer.",
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
            The AI interview platform is the part of BrowseJobs hiring that asks the questions and scores the
            answers. The interview is async and role-specific. The candidate speaks or types. Later rounds use your
            questions or questions generated from the job description. Your panel reads the answers and the scores
            before it spends an hour. The platform does not hire anyone. A person releases the offer.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#call", label: "The interview" },
          { href: "#rounds", label: "L1, L2, and custom rounds" },
          { href: "#brief", label: "The brief your team reads" },
          { href: "#humans", label: "What stays human" },
          { href: "#read", label: "How to read a brief" },
          { href: "#price", label: "Price" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="call" kicker="First conversation" heading="An AI interview, not a phone call we place">
        <p>
          What runs today is an async interview against the rubric for that job. The candidate answers, spoken or
          typed. Questions are generated with the job description and can be previewed before anyone is invited. A
          round goes out by hand, or on its own when a score clears the bar you set. Below the bar, nothing is sent
          and nobody is rejected. An outbound phone dialler is not connected.
        </p>
        <p>
          The interview is an AI interview. We do not dress it up as a recruiter ringing them. We also do not publish
          a show-rate, a pass-rate, or a time saved. This page will not invent one.
        </p>
        <EmployerPipeline ids={["call"]} />
      </MoneySection>

      <MoneySection id="rounds" kicker="The rounds" heading="L1, L2, or a round you design">
        <p>
          After the first interview, further rounds are AI interview, multiple choice, or a human round you run
          yourself. Questions already asked are not asked again. A human round cannot be automatic. There is no
          slot-booking network behind it yet. You record the outcome on the pipeline. Camera and window-switch
          checks are not captured.
        </p>
        <p>
          Use a human round when the signal you need is a conversation your team must have themselves. Use the async
          interview when you need comparable answers before you spend the panel. The platform does not decide which
          of those you value. You do, per role.
        </p>
        <EmployerPipeline ids={["rounds"]} />
      </MoneySection>

      <MoneySection id="brief" kicker="Evidence" heading="A written brief before you meet anyone">
        <p>
          The output your team is meant to read is a graded report: the answers, the dimension scores, and a short
          read of what was strong and what was thin. Until grading finishes, the score stays empty. We do not fill
          a number in to make the shortlist look finished. Hiring-manager comments stay inside your workspace.
        </p>
        <p>
          Camera and window-switch checks are not captured. A blank integrity panel is empty. It is not a pass, and
          it is not a silent rejection. A person still decides. The software will not reject anyone on its own.
        </p>
        <EmployerPipeline ids={["bgv", "report"]} />
        <p className="mono text-[11px] leading-relaxed text-muted">{EMPLOYER_DISCLAIMER}</p>
      </MoneySection>

      <MoneySection id="humans" kicker="The boundary" heading="Automation stops before the offer">
        <p>
          Rules on the pipeline listen for a graded application or a graded round, then advance or park. When you
          save a rule, you see how many current graded applicants already clear its bar. A rule cannot reject
          terminally, and it cannot release an offer. Offer release is an explicit human action from a permitted
          role. That is the line to put in a security review, because it is the line the product actually draws.
        </p>
        <p>
          The interviews sit inside a longer hiring flow: JD structuring, ranking, the pipeline board, and the two
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
          A useful brief answers three questions before you open a calendar. Which rubric dimension carried the
          score, and which one was thin? Are the answers actually there, or is the score still empty because grading
          has not finished? What did the candidate say, in their words, not in a one-line summary?
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
          Role-specific AI interviews are included in the six free months. After that, you either continue on the agency
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
