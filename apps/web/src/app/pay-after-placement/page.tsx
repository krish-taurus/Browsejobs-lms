import type { Metadata } from "next";
import { PromiseCards } from "@/components/landing/PromiseCards";
import { FeeModel } from "@/components/seo/FeeModel";
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
import { verifyChecks } from "@/content/landing";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.payAfter;

const faqs = [
  {
    q: "What does pay after placement mean at BrowseJobs?",
    a: "It means the placement fee is charged only after you accept an offer. That fee is your first three months of CTC, paid as six monthly EMIs, with the ₹30,000 registration adjusted inside it. It does not mean the whole programme is free until you are hired. Registration is a separate ₹30,000, due after three free steps and before the placement fee exists.",
  },
  {
    q: "Do I pay nothing if I am not hired?",
    a: "You do not pay the placement fee if you do not accept an offer. You may already have paid registration. Registration is ₹30,000, or three EMIs of ₹10,000, and it is refundable for 30 days for any reason. After 30 days the published refund policy says registration is non-refundable. Placement fees arise only after you accept an offer, under the enrolment agreement.",
  },
  {
    q: "When is the ₹30,000 due?",
    a: "After the three free steps: counselling with a written Career Analysis Report, the live masterclass, and the 7-hour Python bootcamp. Not before. If someone asks you to pay registration in order to “hold a seat” before those steps, that is not the fee model on this page.",
  },
  {
    q: "Is a job guaranteed if I pay?",
    a: "No. Nobody can guarantee employment. The market decides, and so does your performance. Paying registration buys the programme described on the course page. It does not buy an offer. There is no fixed salary promise and no fabricated experience.",
  },
  {
    q: "How is the placement fee calculated?",
    a: "Take the annual CTC on the offer you accept. Three months of that CTC is the starting figure. Subtract the ₹30,000 registration you have already paid. Divide the remainder by six. That is the EMI. A ₹12 LPA illustration is published on the homepage and repeated on this page so the arithmetic is visible. It is an example, not a salary you have been promised.",
  },
  {
    q: "What is included, and what costs extra?",
    a: "Included with registration: live instructor-led classes, recordings for one year, an AI tutor, MCQ tests and mastery tracking, a first comprehensive CV, the base mock-interview quota, and the student support desk. Optional extras published on the site are extra CV credits at ₹99 for three, voice mock interviews at ₹249 or ₹599 for three, an extra 1:1 mentor session at ₹499, and Career+ at ₹499 a month after placement. Those extras are not the placement fee.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "Pay after placement", path: page.path },
] as const;

export const metadata: Metadata = moneyMetadata(page);

export default function PayAfterPlacementPage() {
  const jsonLd = jsonLdGraph([webPageNode(page), breadcrumbNode(crumbs), faqNode(faqs)]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="Fees · in writing"
        title={page.title}
        crumbs={crumbs}
        primary={{ kind: "masterclass" }}
        secondary={{ href: "/#fees", label: "Fee panel on the homepage" }}
        lede={
          <p>
            Pay after placement, at BrowseJobs, means one of the two fees waits until you accept an offer. It does
            not mean you pay nothing unless you are hired. Registration is ₹30,000 after three free steps. The
            placement fee is the first three months of the CTC on the offer you accept, in six EMIs, with that
            ₹30,000 adjusted inside it. Nobody can guarantee the offer. The market decides.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#two-fees", label: "Two fees, not one slogan" },
          { href: "#free", label: "What is free" },
          { href: "#registration", label: "Registration" },
          { href: "#placement-fee", label: "The placement fee" },
          { href: "#not-a-guarantee", label: "Why this is not a guarantee" },
          { href: "#compare", label: "Compare the promise, not the slogan" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="two-fees" kicker="The model" heading="Two fees. Both in writing. One only after you are hired.">
        <p>
          The homepage states the model in one line, and the line is accurate: two fees, both in writing, one only
          after you are hired. The trouble starts when “pay after placement” is repeated without the first fee.
          Registration is real money. It is smaller than the placement fee on most offers, and it is earlier. If you
          remember only the slogan, you will feel misled on the day the ₹30,000 is due. This page exists so that day
          is boring.
        </p>
        <p>
          Amounts below are the figures published on the site. Checkout still calculates the real charge on the
          server. A page cannot change your invoice. If a person quotes a different registration, a waived placement
          fee, or a fixed salary, ask them to put it in writing and then compare it with this page and with the{" "}
          <TextLink href="/refund-policy">refund policy</TextLink>. The written version wins.
        </p>
      </MoneySection>

      <MoneySection id="free" kicker="Before any payment" heading="Three steps that cost nothing">
        <p>
          You can leave after any of them. The first is free counselling and a written Career Analysis Report: where
          you stand, and what to do, whether you join or not. The report is allowed to point away from our courses.
          The second is a free live masterclass on how the syllabus is rebuilt from real interviews. The third is a
          free 7-hour Python bootcamp, so you have sat a teaching day before you decide. No card is required for
          those three.
        </p>
        <p>
          Counselling calls are recorded and AI-monitored. That is a published standard, not a hidden extra. If you
          want the conversation in person, the office is in Whitefield, Bengaluru. The same steps apply if you are
          studying online from another city. Nothing in the free ladder is a trial that silently converts into a
          charge.
        </p>
      </MoneySection>

      <MoneySection id="registration" kicker="Fee one" heading="₹30,000, only after those three steps">
        <p>
          Registration is ₹30,000 in one payment, or three EMIs of ₹10,000. It is what opens the paid batch: live
          instructor-led classes, recordings for a year, the AI tutor, tests, the first CV, the base mock quota, and
          the support desk. Optional extras — more CV credits, voice mocks, an additional mentor session, Career+
          after you are placed — are priced separately on the homepage. They are not bundled into a surprise at the
          end.
        </p>
        <p>
          The guarantee on this fee is 30 days, any reason, full refund, in writing. The refund policy says to email
          hello@browsejobs.ai or WhatsApp +91 86185 19825 with the subject “Refund”, from your registered contact.
          After 30 days, registration is non-refundable. Read that sentence before you pay, not after. The
          processing window for the refund itself is still marked on the policy page as a placeholder pending the
          founder’s legal review. [Krish: the refund policy still shows “[refund processing window]”. Replace it
          there; this page should not invent a number of days.]
        </p>
      </MoneySection>

      <MoneySection id="placement-fee" kicker="Fee two" heading="Charged only when you accept an offer">
        <p>
          The placement fee is not a flat success bonus printed in the brochure. It is defined as the first three
          months of the CTC on the offer you accept. It is paid as six monthly EMIs from the new salary. The ₹30,000
          you already paid is subtracted, so you are not paying registration twice. If you do not accept an offer,
          this fee is not raised. Completing the course does not create it. Attending interviews does not create it.
          An offer you decline does not create it. Acceptance does.
        </p>
        <p>
          Because the fee depends on a CTC we do not control, we cannot print your number today. The homepage shows
          a worked example at ₹12 LPA so the shape is visible: three months of that CTC is ₹3,00,000, minus ₹30,000
          is ₹2,70,000, which is six EMIs of ₹45,000. Change the offer and the EMIs change. A higher CTC raises the
          fee. A lower CTC lowers it. That is the formula, not a target. The disclaimer under the example is
          mandatory because a salary figure is a stat, even when it is only an illustration.
        </p>
        <FeeModel />
      </MoneySection>

      <MoneySection id="not-a-guarantee" kicker="The limit" heading="A delayed fee is not an offer of work">
        <p>
          It is reasonable to ask why the placement fee is structured this way. The honest answer is alignment: we
          are paid the larger fee when you accept work, so we have a reason to keep a profile moving after the
          classes end. Alignment is not omnipotence. Companies freeze roles. Interviews go badly. People discover,
          correctly, that they do not want the job they trained for. None of those outcomes is fixed by a contract
          that says the second invoice waits.
        </p>
        <p>
          So the sentence we will write, and the sentence we will not write, are different. We will write the
          process, the two amounts, the 30-day window, and the rule that the placement fee follows acceptance. We
          will not write that hiring is certain, that every student is placed, or that a salary is fixed. If you need a
          guarantee in order to resign, do not resign. Stay in the job that pays you until you have an offer you
          have accepted, and treat this programme as a risk you can price.
        </p>
        <p>
          The programmes this fee applies to are the live ones:{" "}
          <TextLink href="/courses/data-engineering">Data Engineering</TextLink>,{" "}
          <TextLink href="/courses/data-analytics">Data Analytics</TextLink>,{" "}
          <TextLink href="/courses/devops-cloud">DevOps & Cloud</TextLink>, and{" "}
          <TextLink href="/courses/python-backend">Python Backend</TextLink>. Choosing among them is a separate
          question, covered on <TextLink href="/non-it-to-it">Non-IT to IT</TextLink> if you are changing domain.
        </p>
      </MoneySection>

      <MoneySection id="compare" kicker="Before you pay anyone" heading="Compare the promise, not the slogan">
        <p>
          Other institutes use “pay after placement” to mean different contracts: a percentage of salary, an ISA
          with a cap, or a fee that starts when you receive an offer rather than when you accept one. Ask which
          event creates the invoice. On this page the event is acceptance. Ask what happens if you are not hired.
          On this page the placement fee is not raised, and the registration you already paid follows the 30-day
          rule. Ask whether a salary is promised. On this page it is not.
        </p>
        <p>The same three checks published for the courses apply when the thing being sold is a fee:</p>
        <ol className="list-decimal space-y-3 pl-5">
          {verifyChecks.map((check) => (
            <li key={check.title}>
              <span className="font-semibold text-ink">
                {check.title} · {check.time}.
              </span>{" "}
              {check.body}
            </li>
          ))}
        </ol>
        <p>
          “Do you guarantee a job?” is the third check, and it is the one this page is for. The answer is no. If a
          comparison call gives you a yes, ask them to put the yes in the contract, including what they pay you if
          the market does not hire you. Then read that clause with someone who is not on the call.
        </p>
      </MoneySection>

      <PromiseCards />
      <MoneyFaq faqs={faqs} />
      <RelatedLinks
        links={[
          { href: "/masterclass", label: "Free masterclass", note: "The second free step. See the method live." },
          { href: "/courses", label: "Programmes", note: "What the registration actually enrols you in." },
          { href: "/refund-policy", label: "Refund policy", note: "The 30-day guarantee, in the legal page." },
          { href: "/data-engineering-course-bangalore", label: "Data Engineering in Bengaluru", note: "The city page, with the same fee." },
          { href: "/non-it-to-it", label: "Which course fits", note: "A switch is not a reason to skip the fee page." },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
