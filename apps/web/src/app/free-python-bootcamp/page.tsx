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
const START = "2026-10-19";
const END = "2026-10-23";
const PATH = "/free-python-bootcamp";
const TITLE = "Free 5-Day Python Bootcamp | BrowseJobs Bangalore";
const DESCRIPTION =
  "Free 5-day live online Python bootcamp, 19–23 Oct 2026, one hour each evening. Score 75%+ and your CV goes to 3,000 HR recruiters.";

const faqs = [
  {
    q: "Is the Python bootcamp free?",
    a: "Yes. The five evenings are free. You do not pay to attend, and signing up does not enrol you on a paid course.",
  },
  {
    q: "How long is each day?",
    a: "One hour, live and online, on five evenings from Monday 19 October to Friday 23 October 2026. It is not the separate 7-hour Python bootcamp named in the free ladder.",
  },
  {
    q: "When does it start?",
    a: "Monday 19 October 2026. The five sessions run 19–23 October 2026, Monday to Friday, one hour each evening, live and online.",
  },
  {
    q: "What is on the five days?",
    a: "Day 1, Monday 19 October 2026, is Python basics. Day 2, Tuesday 20 October 2026, is data types and control flow. Day 3, Wednesday 21 October 2026, is functions and files. Day 4, Thursday 22 October 2026, is pandas basics. Day 5, Friday 23 October 2026, is a mini project plus the AI interview.",
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
  { day: "Day 1", label: "Monday 19 October 2026", iso: "2026-10-19", title: "Python basics" },
  { day: "Day 2", label: "Tuesday 20 October 2026", iso: "2026-10-20", title: "Data types and control flow" },
  { day: "Day 3", label: "Wednesday 21 October 2026", iso: "2026-10-21", title: "Functions and files" },
  { day: "Day 4", label: "Thursday 22 October 2026", iso: "2026-10-22", title: "Pandas basics" },
  { day: "Day 5", label: "Friday 23 October 2026", iso: "2026-10-23", title: "Mini project plus the AI interview" },
] as const;

const onlineLocation = {
  "@type": "VirtualLocation" as const,
  url: `${SITE_ORIGIN}${PATH}`,
};

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
        "Free live online Python bootcamp. Five sessions, one hour each evening, Monday 19 October through Friday 23 October 2026.",
      startDate: START,
      endDate: END,
      eventAttendanceMode: "https://schema.org/OnlineEventAttendanceMode",
      eventStatus: "https://schema.org/EventScheduled",
      url: `${SITE_ORIGIN}${PATH}`,
      isAccessibleForFree: true,
      location: onlineLocation,
      organizer: { "@id": ORGANIZATION_ID },
      offers: {
        "@type": "Offer",
        price: "0",
        priceCurrency: "INR",
        url: `${SITE_ORIGIN}${PATH}`,
      },
      subEvent: days.map((item) => ({
        "@type": "Event" as const,
        name: `${item.day}: ${item.title}`,
        description: `${item.title}. One hour, live and online, on the evening of ${item.label}.`,
        startDate: item.iso,
        endDate: item.iso,
        duration: "PT1H",
        eventAttendanceMode: "https://schema.org/OnlineEventAttendanceMode",
        eventStatus: "https://schema.org/EventScheduled",
        url: `${SITE_ORIGIN}${PATH}`,
        isAccessibleForFree: true,
        location: onlineLocation,
        organizer: { "@id": ORGANIZATION_ID },
      })),
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
              A free 5-day Python bootcamp, live and online, one hour each evening, Monday 19 October through Friday 23
              October 2026. It is for people who want to sit a short class before a six-month course. The phone is{" "}
              {OFFICE_PHONE.display}.
            </p>
          </>
        }
      />

      <MoneySection id="when" kicker="Start date" heading="Monday 19 October 2026">
        <p>
          The first session is <time dateTime={START}>Monday 19 October 2026</time>. Five daily sessions run{" "}
          <time dateTime={START}>19 October</time> through <time dateTime={END}>23 October 2026</time>, Monday to
          Friday, one hour each evening, live and online.
        </p>
        <p>
          The order is Python basics; data types and control flow; functions and files; pandas basics; a mini project
          plus the AI interview.
        </p>
      </MoneySection>

      <MoneySection id="agenda" kicker="Agenda" heading="The five evenings">
        <ol>
          {days.map((item) => (
            <li key={item.iso}>
              <strong>
                {item.day}, <time dateTime={item.iso}>{item.label}</time>. {item.title}.
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
          phone you about a seat on the 19–23 October 2026 cohort.
        </p>
        <ApCounselForm source="free-python-bootcamp" />
      </MoneySection>

      <MoneySection id="courses" kicker="After the bootcamp" heading="The six-month courses">
        <p>
          The bootcamp is not a course enrolment. If you still want a six-month programme, these are the pages:{" "}
          <TextLink href="/courses/data-engineering">Data Engineering</TextLink>,{" "}
          <TextLink href="/courses/data-analytics">Data Analytics</TextLink>,{" "}
          <TextLink href="/courses/agentic-ai-course">Agentic AI</TextLink>, and{" "}
          <TextLink href="/courses/gen-ai">Gen AI</TextLink>. Gen AI publishes Python, LLMs, RAG and AI agents on its own course page.
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
          { href: "/courses/gen-ai", label: "Gen AI", note: "Six months in Bangalore. Pay after placement." },
          { href: "/get-hired", label: "Free AI interview", note: "The 75% mark, before a course." },
          { href: "/founder", label: "Dr Krish Bharggav", note: "Founder." },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
