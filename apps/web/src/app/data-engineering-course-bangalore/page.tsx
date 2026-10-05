import type { Metadata } from "next";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { PromiseCards } from "@/components/landing/PromiseCards";
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
import { placementChannels } from "@/content/courses";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, dataEngineeringCourseNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.deBangalore;

const faqs = [
  {
    q: "What is the best data engineering course in Bangalore?",
    a: "“Best” is the wrong test. Check three things: the syllabus matches job descriptions you can open today, the classes are actually live, and every fee is in writing. BrowseJobs publishes a six-month live Data Engineering syllabus — SQL, Python, Spark, Databricks, AWS, Azure, and Airflow — taught online, with an office in Whitefield, Bengaluru. Registration is ₹30,000 after three free steps. The placement fee is due only after you accept an offer. Nobody can guarantee you a job.",
  },
  {
    q: "Is the Data Engineering course taught in Whitefield, or online?",
    a: "The published format is live online, with recordings. The office is in Whitefield, Bengaluru, Karnataka 560066, for counselling and a conversation. You do not need to commute to Whitefield for every class. Hours are Monday to Saturday, 9:00 AM to 7:00 PM IST.",
  },
  {
    q: "How long is the data engineering course, and what does it cover?",
    a: "Six months. The published modules run from Python and Pandas through SQL and data modelling, Apache Spark and PySpark, AWS, Databricks and Delta Lake, orchestration including Airflow, and Microsoft Azure data engineering. Interview practice is a module, not an extra. Kafka and streaming are self-study, not the core syllabus.",
  },
  {
    q: "What does pay after placement mean on this course?",
    a: "It does not mean the course is free until you are hired. Registration is ₹30,000, or three EMIs of ₹10,000, payable after the free masterclass and the free 7-hour Python bootcamp. The placement fee — the first three months of the CTC on the offer you accept, minus that ₹30,000, in six EMIs — is due only after you accept an offer. If you do not accept an offer, that second fee is not charged.",
  },
  {
    q: "Do you guarantee a data engineering job in Bengaluru?",
    a: "No. Nobody can guarantee employment. The market decides, and so does your performance. What is in writing is the process: a syllabus rebuilt from monitored interviews, live teaching, mock interviews, and a placement team. There is no fixed salary promise and no fabricated experience.",
  },
  {
    q: "I am not from an IT background. Can I start with this course?",
    a: "Sometimes. Data Engineering is the heavier of the data tracks: Spark, cloud, and orchestration. If your work today is Excel and reporting, Data Analytics is usually the closer start. Book the free counselling. You leave with a written Career Analysis Report whether you join or not, including when the honest recommendation is a path we do not teach.",
  },
  {
    q: "Where do I read the full syllabus and the Bengaluru salary context?",
    a: "The module list on this page is the published brochure syllabus. The course page is /courses/data-engineering. Illustrative pay bands for a data engineer in Bengaluru are on /salaries/data-engineer-bengaluru. Those bands are benchmarks with a disclaimer. They are not an offer.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "Data Engineering, Bengaluru", path: page.path },
] as const;

export const metadata: Metadata = moneyMetadata(page);

export default function DataEngineeringBangalorePage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode(crumbs),
    dataEngineeringCourseNode(),
    faqNode(faqs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="Data Engineering · Bengaluru"
        title={page.title}
        crumbs={crumbs}
        primary={{ kind: "masterclass" }}
        secondary={{ href: "/courses/data-engineering", label: "Open the course page" }}
        lede={
          <p>
            BrowseJobs teaches Data Engineering as a live, six-month programme for people who want to work in
            Bengaluru. Classes are instructor-led and online, with recordings kept for a year. The office is in
            Whitefield. You pay the ₹30,000 registration only after the free counselling, the free masterclass, and
            the free Python bootcamp. The placement fee — the first three months of your CTC — is due only after you
            accept an offer. Nobody can guarantee you a job.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#city", label: "Bengaluru and Whitefield" },
          { href: "#syllabus", label: "Six-month syllabus" },
          { href: "#projects", label: "Projects and your CV" },
          { href: "#who", label: "Who it is for" },
          { href: "#fees", label: "What you pay" },
          { href: "#placement", label: "After you can interview" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="city" kicker="The city" heading="Bengaluru, Bangalore, and Whitefield are the same search">
        <p>
          People look for this course under three names: data engineering course in Bangalore, data engineering
          course in Bengaluru, and data engineering course in Whitefield. This page is that course. The city on our
          contact line and on the salary page is Bengaluru. Bangalore is the name still used in most job searches.
          Whitefield is the neighbourhood of the office.
        </p>
        <p>
          The published class format is live online plus recordings, with one year of access. You do not have to sit
          in Whitefield for every session. The office is where counselling happens and where you can come in. The
          address published on the site is Whitefield, Bengaluru, Karnataka 560066. The phone is +91 86185 19825.
          The hours are Monday to Saturday, 9:00 AM to 7:00 PM IST. Email is hello@browsejobs.ai.
        </p>
        <p>
          Bengaluru is described on our data-engineer salary page as India&apos;s data-platform capital, where GCCs,
          product companies, and AI startups hire people who can build pipelines. That sentence is context for why
          the city shows up in the search, not a claim that a seat on this course produces an offer from any of
          those companies. Read the bands themselves on{" "}
          <TextLink href="/salaries/data-engineer-bengaluru">the Bengaluru data engineer salary page</TextLink>. They
          are illustrative. The disclaimer on that page applies, and it applies here if you treat a band as a
          promise.
        </p>
        <p>
          If you are comparing institutes in the city, use the three checks we already publish. Count demand: open a
          job board, search the role and the city, filter to the last seven days, and look at the number. Read five
          job descriptions and note the skills that repeat, then hold them against any syllabus, including ours. Ask
          every institute whether every promise is in writing, whether the success rate is defined, and whether they
          will guarantee a job. We will not guarantee a job. The written answer is on this page.
        </p>
      </MoneySection>

      <MoneySection id="syllabus" kicker="The syllabus" heading="What the six months actually cover">
        <p>
          The course page states the length as six months and the outcomes in plain language: design and ship
          production batch data pipelines on AWS and Azure; answer the SQL, Spark, and system-design questions being
          asked; build and orchestrate medallion-architecture ETL on Databricks and Delta Lake; walk an interviewer
          through projects you built; and explain the work clearly. Communication is a scored module.
        </p>
        <p>
          The syllabus is not a fixed PDF from a previous year. It is rebuilt around what interviews are asking. An
          AI system monitors up to ~50 real and mock interviews a day and extracts the questions. That number is
          BrowseJobs internal data. It is not a placement rate, and it is not a promise that those interviews become
          your interviews.
        </p>
        <Disclaimer />
        <p>
          The tools named on the course page are Python, SQL, Pandas, PySpark, Apache Spark, AWS (S3, Glue, Redshift,
          Lambda, EC2), Databricks, Delta Lake, Azure (ADF, Synapse, ADLS Gen2), Airflow, and Git. Below is the
          module list as published. If a line is not in that list, it is not something we are claiming you will be
          taught.
        </p>
        <CourseSyllabus slug="data-engineering" />
      </MoneySection>

      <MoneySection id="projects" kicker="Proof you can talk about" heading="Three projects. No invented employment.">
        <p>
          The course is marked “3 CV-ready” projects: an end-to-end batch pipeline from raw data on S3 through Glue
          into Redshift and a live dashboard; a Bronze, Silver, and Gold lakehouse on Databricks with Delta Lake and
          slowly changing dimensions; and a production Airflow deployment with retries, alerting, and parameterised
          runs. The point of the projects is that you can explain them, because you built them.
        </p>
        <p>
          They are not a substitute for a job you did not have. We will not adjust your experience, and we will not
          put a company on your CV that did not employ you. If you are switching domain, the published line is that
          the projects become the centre of the CV as genuine work in the new domain, and that they are safe to
          defend in a background check. A project is still a project. An interviewer is allowed to ask who owned
          production, and you should answer that honestly.
        </p>
        <p>
          The full course page, including how the modules are taught, is{" "}
          <TextLink href="/courses/data-engineering">Data Engineering</TextLink>. This landing page does not replace
          it. It answers the city query and points at the same syllabus.
        </p>
      </MoneySection>

      <MoneySection id="who" kicker="Fit" heading="Who should take it in Bengaluru — and who should not">
        <p>
          The roles named on the course are Data Engineer, Big Data Engineer, ETL Developer, Analytics Engineer, and
          Cloud Data Engineer. The programme fits you if you can give it six months of live classes, you are willing
          to be scored on mocks before anyone calls you interview-ready, and you want the batch tools above rather
          than a dashboard-only job.
        </p>
        <p>
          It is a weaker start if your current work is spreadsheets and business reporting and you have not written
          code. That profile is closer to{" "}
          <TextLink href="/courses/data-analytics">Data Analytics</TextLink>, which publishes Excel, SQL, Python,
          statistics, and Power BI over five to six months. Switching the other way — from analytics into
          engineering — is a common reason people book counselling. The written Career Analysis Report is free, and
          it is allowed to recommend a path we do not sell.
        </p>
        <p>
          Freshers are not turned away by a rule on this page. The published fresher line is a free internship
          certificate for hands-on work, described as the equivalent of six months of practice before a first
          interview. Read that as a certificate for work you do on the programme, not as an offer letter. A career
          gap is also not a bar by itself. A structured internship built around projects you deliver can fill a
          timeline. An empty claim cannot. The longer version of that decision sits on{" "}
          <TextLink href="/non-it-to-it">Non-IT to IT</TextLink>.
        </p>
        <p>
          If you live outside Bengaluru and want the same syllabus without treating this as a city page, use{" "}
          <TextLink href="/data-engineering-course-india">Data Engineering course in India</TextLink>. The teaching
          is the same live online programme. The office for an in-person conversation remains Whitefield.
        </p>
      </MoneySection>

      <MoneySection id="fees" kicker="Fees" heading="What you pay, and what “pay after placement” does not mean">
        <p>
          There are two fees. Registration is ₹30,000, or three payments of ₹10,000. It is payable only after the
          three free steps: counselling with a written report, the live masterclass, and the 7-hour Python bootcamp.
          The placement fee is separate. It is the first three months of the CTC on the offer you accept, paid as six
          monthly EMIs from the new salary, with the ₹30,000 adjusted inside it. You do not owe that second fee
          because you finished the course. You owe it because you accepted an offer.
        </p>
        <p>
          There is a 30-day money-back guarantee on registration, any reason, in writing. After 30 days the
          registration is non-refundable, which is the refund policy as published. Nothing on this page changes that
          policy. The full fee page is <TextLink href="/pay-after-placement">Pay after placement</TextLink>. The
          legal wording is on the <TextLink href="/refund-policy">refund policy</TextLink>.
        </p>
        <FeeModel />
      </MoneySection>

      <MoneySection id="placement" kicker="After the course" heading="What the placement process is — and is not">
        <p>
          Readiness is described as a decision on your mock data, not on a sales target. Weekly technical and HR
          mocks are part of the interview module, and they are AI-analysed. When you are put forward, three channels
          are published with the syllabus:
        </p>
        <ul className="list-disc space-y-2 pl-5">
          {placementChannels.map((channel) => (
            <li key={channel.title}>
              <span className="font-semibold text-ink">{channel.title}.</span> {channel.body}
            </li>
          ))}
        </ul>
        <p>
          One of those channels is described on the course content as a network of 3,000+ HR contacts. Treat that
          figure the way you should treat every other count on this site: it is internal data, it is historical, and
          it does not mean those contacts are obliged to interview you.
        </p>
        <Disclaimer />
        <p>
          Classes are live and instructor-led. Recordings exist so a missed class is recoverable, not so that a
          recording can be sold as a live batch. If you miss a class, the published answer is that every class is
          recorded and searchable, and access runs a full year.
        </p>
      </MoneySection>

      <PromiseCards />

      <MoneyFaq faqs={faqs} />
      <RelatedLinks
        links={[
          {
            href: "/courses/data-engineering",
            label: "Data Engineering course page",
            note: "The live syllabus, tools, and projects.",
          },
          {
            href: "/salaries/data-engineer-bengaluru",
            label: "Data engineer salary in Bengaluru",
            note: "Illustrative bands. Read them with the disclaimer.",
          },
          {
            href: "/data-engineering-course-india",
            label: "Data Engineering course in India",
            note: "Same programme, framed for people studying online from other cities.",
          },
          {
            href: "/pay-after-placement",
            label: "Pay after placement",
            note: "Registration, the placement fee, and the 30-day guarantee.",
          },
          {
            href: "/non-it-to-it",
            label: "Non-IT to IT",
            note: "Which track fits a career switch, including a gap.",
          },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
