import type { Metadata } from "next";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { CourseSyllabus } from "@/components/seo/CourseSyllabus";
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
import { salaryPages } from "@/content/salaries";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, dataEngineeringCourseNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.deIndia;

const salaryLinks = salaryPages.filter((item) => item.role === "Data Engineer");

const faqs = [
  {
    q: "Is there an online data engineering course for people outside Bengaluru?",
    a: "Yes. The published format of the BrowseJobs Data Engineering programme is live online, with recordings, for six months. You can study from any city in India. Counselling and the office are in Whitefield, Bengaluru. The syllabus is the same one published for the Bengaluru page: Python, SQL, Spark, Databricks, AWS, Azure, and Airflow.",
  },
  {
    q: "Do I have to travel to Bengaluru to enrol?",
    a: "You do not have to travel for classes. They are live and online. If you want the counselling conversation in person, the office is in Whitefield, Bengaluru, Karnataka 560066, Monday to Saturday, 9:00 AM to 7:00 PM IST. The phone is +91 86185 19825.",
  },
  {
    q: "Is the fee different outside Bengaluru?",
    a: "No separate city price is published. Registration is ₹30,000 after the three free steps, or three EMIs of ₹10,000. The placement fee is the first three months of the CTC on the offer you accept, in six EMIs, with the ₹30,000 adjusted inside it. It is due only after you accept. It is not a different number because of your city.",
  },
  {
    q: "Will you place me in my home city?",
    a: "Nobody can guarantee employment in any city. Hiring depends on the live market and on your performance. Illustrative salary bands are published for data engineers in Bengaluru, Hyderabad, and Pune. Those pages are benchmarks with a disclaimer. They are not a list of cities where an offer is certain.",
  },
  {
    q: "What is not in the India syllabus?",
    a: "Kafka and Spark Structured Streaming are not core modules. They are listed as self-study for the day a specific job description asks for streaming. If a brochure or an advertisement adds tools that are not on the course page, ask us to put the correction in writing before you pay.",
  },
  {
    q: "How is this page different from the Bengaluru page?",
    a: "The programme is the same. The Bengaluru page answers people searching for a Whitefield or Bangalore classroom. This page answers people searching for a national, online course who still want a real office behind the counselling. Both link to /courses/data-engineering, which is the syllabus.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "Data Engineering, India", path: page.path },
] as const;

export const metadata: Metadata = moneyMetadata(page);

export default function DataEngineeringIndiaPage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode(crumbs),
    dataEngineeringCourseNode(),
    faqNode(faqs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="Data Engineering · India"
        title={page.title}
        crumbs={crumbs}
        primary={{ kind: "masterclass" }}
        secondary={{ href: "/data-engineering-course-bangalore", label: "Bengaluru and Whitefield" }}
        lede={
          <p>
            If you are in India and you cannot sit in Bengaluru every weekday, the Data Engineering programme is
            still the live six-month course: instructor-led classes online, recordings for a year, and the same
            published syllabus. Counselling and the office are in Whitefield, Bengaluru. Registration is ₹30,000
            after three free steps. The placement fee is due only after you accept an offer. A job, in any city, is
            not guaranteed.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#online", label: "Online, with a Bengaluru office" },
          { href: "#syllabus", label: "The national syllabus" },
          { href: "#cities", label: "Cities and salary pages" },
          { href: "#fees", label: "The fee, wherever you live" },
          { href: "#honest", label: "What we will not claim" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="online" kicker="How it runs" heading="Live online teaching. Counselling from Bengaluru.">
        <p>
          The course record says the format is “Live online + recordings” and access is “1 year unlimited”. That is
          the national delivery. There is no second, thinner syllabus for people outside Karnataka. There is also no
          published promise of a classroom batch in every metro. If someone tells you there is, ask for it in
          writing and compare it with this page.
        </p>
        <p>
          The company is IBrowseJobs Technologies Pvt Ltd. The published address is Whitefield, Bengaluru, Karnataka
          560066. Use it when you want a person in the room: the free counselling that produces a written Career
          Analysis Report. You can also do that conversation remotely. The report is yours whether you join or not,
          including when the recommendation is a track we do not teach, or no course at all.
        </p>
        <p>
          The free steps before any payment are the same everywhere. First, counselling and the written report.
          Second, a live masterclass on how the syllabus is reverse-engineered from real interviews. Third, a
          7-hour Python bootcamp so you have sat a teaching day before you decide. Registration comes after those
          three. The masterclass is the step every marketing call is supposed to point at. Book it from this page.
          Do not pay a counsellor who asks for the ₹30,000 before those steps.
        </p>
        <p>
          Classes are live. A missed session is a recording, not a reason to pretend you attended. The published
          rule is that recordings are searchable by topic. Time zone for the office, and for the hours we answer, is
          IST: Monday to Saturday, 9:00 AM to 7:00 PM.
        </p>
      </MoneySection>

      <MoneySection id="syllabus" kicker="Syllabus" heading="SQL, Python, Spark, Databricks, AWS, Azure, Airflow">
        <p>
          The six-month angle is batch data engineering, because that is what the monitored interviews are described
          as testing. Python for data engineering, Pandas, SQL and data modelling, Apache Spark and PySpark, AWS for
          data engineering, Databricks and Delta Lake, advanced Databricks with Airflow and ADF orchestration, Azure
          data engineering, and an interview module. Roles named on the course are Data Engineer, Big Data Engineer,
          ETL Developer, Analytics Engineer, and Cloud Data Engineer.
        </p>
        <p>
          An AI system monitors up to ~50 real and mock interviews a day, and the syllabus is rebuilt around what
          those interviews ask. The figure is internal. It describes how the material moves. It does not describe
          your odds of being hired next quarter.
        </p>
        <Disclaimer />
        <p>
          Three projects are published with the syllabus: a batch pipeline on AWS, a medallion lakehouse on
          Databricks, and a production Airflow deployment. They are work you can discuss because you did it. They
          are not employment we will invent for a background check. Streaming tools — Kafka, Structured Streaming,
          watermarks — stay in the self-study note under the modules. A job description that leads with streaming
          is a reason to read that note with a mentor, not a reason for us to pretend it was week one.
        </p>
        <div className="pg-syllabus">
          <CourseSyllabus slug="data-engineering" />
        </div>
        <p>
          The canonical syllabus URL is <TextLink href="/courses/data-engineering">/courses/data-engineering</TextLink>.
          If you specifically want the Whitefield framing, use{" "}
          <TextLink href="/data-engineering-course-bangalore">the Bengaluru page</TextLink>.
        </p>
      </MoneySection>

      <MoneySection id="cities" kicker="Markets" heading="Salary pages we actually publish">
        <p>
          We do not publish a data-engineer salary page for every Indian city. Inventing a band for a city we have
          not written up would be a fake number. The pages that exist today for this role are below. Each one is an
          illustrative benchmark. Read the disclaimer on the page before you repeat a figure to your family.
        </p>
        <ul className="list-disc space-y-2 pl-5">
          {salaryLinks.map((item) => (
            <li key={item.slug}>
              <TextLink href={`/salaries/${item.slug}`}>
                {item.role} salary in {item.city}
              </TextLink>
              . {item.blurb}
            </li>
          ))}
        </ul>
        <Disclaimer />
        <p>
          Hyderabad and Pune are on that list because the dataset includes them, not because we run a local campus
          there. If your city is missing, that is a gap in the published benchmark set. [Krish: add a salary page
          only when you have a sourced band. Do not backfill cities on this landing page.] Your counselling report
          can still talk about the roles hiring where you live. The report is allowed to say the local market is
          thin.
        </p>
      </MoneySection>

      <MoneySection id="fees" kicker="Fees" heading="One fee model for the country">
        <p>
          Registration does not change with PIN code. ₹30,000 after the free steps, or three EMIs of ₹10,000. Thirty
          days to ask for that registration back, any reason. The placement fee is not a flat “success charge”
          printed in advance, because it is defined as the first three months of the CTC you actually accept, minus
          the registration you already paid, split into six EMIs. If you never accept an offer, that fee is not
          raised. Finishing the six months does not create it.
        </p>
        <p>
          A worked example at ₹12 LPA is on the fee block because the homepage already shows it. Copying it here
          keeps the arithmetic in one place. It is not a salary target for India, and it is not what we expect you
          to be offered. The{" "}
          <TextLink href="/pay-after-placement">pay-after-placement explainer</TextLink> walks through the same rules
          without the city framing.
        </p>
        <FeeModel />
      </MoneySection>

      <MoneySection id="honest" kicker="Limits" heading="What a national page will not say">
        <p>
          We will not say that hiring is certain, that every state has the same demand, or that a remote batch is a
          shortcut around a weak market. We will not say that hiring is certain, or that every student is placed. We will not quote a
          salary as if it were attached to your seat. The red card on the homepage is the short version: nobody
          honestly can guarantee employment, we never fabricate employment, there is no fixed salary promise, and
          there is no claim that hiring is certain.
        </p>
        <p>
          What we will put next to the teaching is the process. Live classes. A syllabus that is supposed to move
          when interviews move. Mocks scored on data. A placement team that works a profile through the channels
          published on the course: a Naukri profile built by the team, a share across the recruiter network, and a
          share with client companies hiring for the role. Those channels are effort. They are not a slot with your
          name on it.
        </p>
        <p>
          If you are changing career rather than changing city, start with{" "}
          <TextLink href="/non-it-to-it">which course fits a non-IT background</TextLink>. Data Engineering is often
          the wrong first step for someone whose work is entirely in Excel. Data Analytics exists for that reason.
          DevOps exists for people who already live near infrastructure. Choosing the famous title and then
          struggling for six months is a worse outcome than a boring, accurate recommendation.
        </p>
      </MoneySection>

      <MoneyFaq faqs={faqs} />
      <RelatedLinks
        links={[
          {
            href: "/courses/data-engineering",
            label: "Data Engineering syllabus",
            note: "Modules, tools, and projects as published.",
          },
          {
            href: "/data-engineering-course-bangalore",
            label: "Bengaluru and Whitefield",
            note: "The same course, written for the city search.",
          },
          {
            href: "/salaries/data-engineer-bengaluru",
            label: "Bengaluru salary bands",
            note: "Illustrative. Hyderabad and Pune pages sit beside it.",
          },
          {
            href: "/pay-after-placement",
            label: "How the fee works",
            note: "Registration, placement fee, refund window.",
          },
          {
            href: "/masterclass",
            label: "Free masterclass",
            note: "See the method before you pay anything.",
          },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
