import type { Metadata } from "next";
import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ApCounselForm } from "@/components/ap/ApCounselForm";
import "@/components/ap/pages/marketing.css";
import {
  ContactStrip,
  MoneyArticle,
  MoneyFaq,
  MoneyHero,
  MoneySection,
  RelatedLinks,
  TextLink,
} from "@/components/seo/MoneyArticle";
import { publishedAddressLine } from "@/content/entity";
import { OFFICE_PHONE } from "@/content/phones";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, ORGANIZATION_ID, SITE_ORIGIN, webPageNode } from "@/lib/seo";

const UPDATED = "2026-10-14";
const PATH = "/free-python-bootcamp";
const TITLE = "Free 5-Day Python Bootcamp | BrowseJobs Bangalore";
const DESCRIPTION =
  "Free 5-day live online Python bootcamp, one hour each evening. Start date not set. Score 75%+ and your CV goes to 3,000 HR recruiters.";

const faqs = [
  {
    q: "Is the Python bootcamp free?",
    a: "Yes. The five evenings are free. You do not pay to attend, and signing up does not enrol you on a paid course.",
  },
  {
    q: "How long is each day?",
    a: "One hour, live and online, on five evenings. It is not the separate 7-hour Python bootcamp named in the free ladder.",
  },
  {
    q: "When does it start?",
    a: "The start date on this page is a placeholder. Dr Krish Bharggav has not set the first cohort yet. The form saves your details so we can call you when the date is published.",
  },
  {
    q: "What is on the five days?",
    a: "Day 1 is Python basics. Day 2 is data types and control flow. Day 3 is functions and files. Day 4 is pandas basics. Day 5 is a mini project plus the AI interview.",
  },
  {
    q: "What does the AI interview do?",
    a: "Scoring 75% or above on the AI interview gives an almost 60% higher chance of an interview call and sends your CV to 3,000 HR recruiters. Below 75% you get a free counselling roadmap. That comparison is BrowseJobs internal data. A person always releases an offer letter. Nobody can guarantee a job.",
  },
  {
    q: "Who do I call?",
    a: `The office line is ${OFFICE_PHONE.display}. The office is ${publishedAddressLine}. Hours are Monday to Saturday, 9:00 AM to 7:00 PM IST.`,
  },
] as const;

const days = [
  { day: "Day 1", title: "Python basics" },
  { day: "Day 2", title: "Data types and control flow" },
  { day: "Day 3", title: "Functions and files" },
  { day: "Day 4", title: "Pandas basics" },
  { day: "Day 5", title: "Mini project plus the AI interview" },
] as const;

const page = {
  path: PATH,
  title: "Free 5-Day Python Bootcamp",
  description: DESCRIPTION,
};

export const metadata: Metadata = moneyMetadata({
  path: PATH,
  title: TITLE,
  description: DESCRIPTION,
  absoluteTitle: true,
});

export default function FreePythonBootcampPage() {
  const crumbs = [
    { name: "Home", path: "/" },
    { name: "Free Python bootcamp", path: PATH },
  ];
  const jsonLd = jsonLdGraph([
    webPageNode({ ...page, title: TITLE, dateModified: UPDATED }),
    breadcrumbNode(crumbs),
    {
      "@type": "Event",
      name: "Free 5-Day Python Bootcamp",
      description:
        "Free live online Python bootcamp. One hour each evening for five days. The start date is not published yet.",
      eventAttendanceMode: "https://schema.org/OnlineEventAttendanceMode",
      eventStatus: "https://schema.org/EventScheduled",
      url: `${SITE_ORIGIN}${PATH}`,
      isAccessibleForFree: true,
      location: {
        "@type": "VirtualLocation",
        url: `${SITE_ORIGIN}${PATH}`,
      },
      organizer: { "@id": ORGANIZATION_ID },
      offers: {
        "@type": "Offer",
        price: "0",
        priceCurrency: "INR",
        url: `${SITE_ORIGIN}${PATH}`,
      },
    },
    faqNode(faqs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="Free · live online"
        title={TITLE}
        crumbs={crumbs}
        primary={{ kind: "link", href: "#sign-up", label: "Request a seat" }}
        secondary={{ href: "/get-hired", label: "Take the free AI interview" }}
        lede={
          <>
            <p className="fine">
              Updated <time dateTime={UPDATED}>14 Oct 2026</time>
              {" · "}
              <Link href="/founder">Dr Krish Bharggav</Link>
            </p>
            <p>
              A free 5-day Python bootcamp, live and online, one hour each evening. It is for people who want to sit a
              short class before a six-month course. The phone is {OFFICE_PHONE.display}.
            </p>
          </>
        }
      />

      <MoneySection id="when" kicker="Start date" heading="Start date: not set yet">
        <p>
          <strong>Placeholder.</strong> Dr Krish Bharggav has not set the first cohort date. This line will be replaced
          when he does. Do not treat any other date you have seen elsewhere as the start of this bootcamp.
        </p>
        <p>
          The class is live online. Each evening is one hour. Five days, in this order: Python basics; data types and
          control flow; functions and files; pandas basics; a mini project plus the AI interview.
        </p>
      </MoneySection>

      <MoneySection id="agenda" kicker="Agenda" heading="The five evenings">
        <ol>
          {days.map((item) => (
            <li key={item.day}>
              <strong>
                {item.day}. {item.title}.
              </strong>
            </li>
          ))}
        </ol>
      </MoneySection>

      <MoneySection id="interview" kicker="The interview" heading="What 75% means on this page">
        <p>
          Scoring 75% or above on the AI interview gives an almost 60% higher chance of an interview call, and your CV
          goes to 3,000 HR recruiters. Below 75%, you get a free counselling roadmap.
        </p>
        <Disclaimer />
        <p>
          A person always releases the offer letter. This bootcamp does not guarantee a job, a salary, or an interview.
        </p>
      </MoneySection>

      <MoneySection id="sign-up" kicker="Seat" heading="Request a seat">
        <p>
          This is the same callback form as the homepage. Leave your name, email, phone, city, and a time to call. We
          phone you when Dr Krish Bharggav sets the start date.
        </p>
        <ApCounselForm source="free-python-bootcamp" />
      </MoneySection>

      <MoneySection id="courses" kicker="After the bootcamp" heading="The six-month courses">
        <p>
          The bootcamp is not a course enrolment. If you still want a six-month programme, these are the pages:{" "}
          <TextLink href="/courses/data-engineering">Data Engineering</TextLink>,{" "}
          <TextLink href="/courses/data-analytics">Data Analytics</TextLink>,{" "}
          <TextLink href="/courses/agentic-ai-course">Agentic AI</TextLink>, and{" "}
          <TextLink href="/gen-ai-course-bangalore">Gen AI</TextLink>. Gen AI on this site is the Agentic AI syllabus.
          Registration on those courses is ₹30,000 and covers the course. Pay after placement is three months of CTC,
          charged in EMIs only after you accept an offer.
        </p>
      </MoneySection>

      <MoneyFaq faqs={faqs} />
      <RelatedLinks
        links={[
          { href: "/courses/data-engineering", label: "Data Engineering", note: "Six months. Pipelines and the modern data stack." },
          { href: "/courses/data-analytics", label: "Data Analytics", note: "Six months. Excel, SQL, Python, Power BI." },
          { href: "/courses/agentic-ai-course", label: "Agentic AI", note: "Six months. Python, LLMs, RAG, AI agents." },
          { href: "/gen-ai-course-bangalore", label: "Gen AI in Bangalore", note: "The same Agentic AI syllabus." },
          { href: "/get-hired", label: "Free AI interview", note: "The 75% mark, before a course." },
          { href: "/founder", label: "Dr Krish Bharggav", note: "Founder." },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
