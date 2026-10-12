import type { Metadata } from "next";
import { PromiseCards } from "@/components/landing/PromiseCards";
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
import { situationCards } from "@/content/courses";
import { courses, verifyChecks } from "@/content/landing";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.nonIt;

const liveCourses = courses.filter((course) => course.live);

const rows: { background: string; start: string; href: string; why: string }[] = [
  {
    background: "Mechanical, civil, electrical, or another non-IT degree. You like structured problems and have not worked in software.",
    start: "Data Analytics",
    href: "/courses/data-analytics",
    why: "The published syllabus is Excel, SQL, Python for analysis, statistics, and Power BI. It is closer to reporting work than Spark and cloud pipelines are.",
  },
  {
    background: "Commerce, accounts, operations, or any job that already lives in spreadsheets.",
    start: "Data Analytics",
    href: "/courses/data-analytics",
    why: "You already defend numbers. The course adds SQL, a statistical vocabulary, and dashboards. It does not assume you have shipped production code.",
  },
  {
    background: "You write small scripts, or you are in IT support and want to build data systems rather than report from them.",
    start: "Data Engineering",
    href: "/courses/data-engineering",
    why: "Six months of SQL, Python, Spark, Databricks, AWS, Azure, and Airflow. Heavier than analytics. Wrong if you wanted a dashboard job.",
  },
  {
    background: "You look after servers, deployments, access, or internal IT, and you want cloud and release work.",
    start: "DevOps & Cloud",
    href: "/courses/devops-cloud",
    why: "The published tools are Docker, Kubernetes, Terraform, Jenkins, and AWS, plus Linux. A different job from data engineering.",
  },
  {
    background: "You already enjoy programming and you want API and database work.",
    start: "Python Backend",
    href: "/courses/python-backend",
    why: "The track is live. The public description is APIs, databases, and production Python. The full module list is not on the site yet.",
  },
  {
    background: "You have a career gap and you are worried it will end the process before a syllabus matters.",
    start: "Free counselling first",
    href: "/masterclass",
    why: "A gap is not a rule that bars you. The written report is free whether you join or not. We will not invent a job to fill the dates.",
  },
  {
    background: "You are a fresher with a degree and little or no work to point at.",
    start: "Free counselling first",
    href: "/masterclass",
    why: "The site describes a free internship certificate for hands-on work. That is a certificate for work you do here. It is not an offer letter.",
  },
];

const faqs = [
  {
    q: "Which course should I take to move from non-IT to IT?",
    a: "There is no single course. BrowseJobs runs four live programmes: Data Analytics, Data Engineering, DevOps & Cloud, and Python Backend. Analytics is the usual start from Excel, commerce, or a non-IT degree. Data Engineering is the start when you want pipelines and you can handle Spark and cloud. DevOps is the start from infrastructure. Python Backend is the start when you want to build APIs, and its full module list is not published yet. The free written Career Analysis Report is how you choose. It is allowed to recommend a path we do not teach.",
  },
  {
    q: "Can I switch to IT after a career gap or a gap year?",
    a: "A gap does not disqualify you on this site. The published line is that a structured internship, built around projects you actually deliver, can fill a timeline with documented work, and that a gap is only a gap if it is empty. We will not fabricate employment to hide the gap. Background verification should be able to survive your CV.",
  },
  {
    q: "I am from a mechanical, civil, or commerce background. Where do I start?",
    a: "Start with the free counselling, not with a payment. If your work is logic, reporting, or spreadsheets, Data Analytics is the programme whose syllabus matches that background. Data Engineering is a steeper first step. Do not pick it because the job title sounds senior.",
  },
  {
    q: "Will you add fake experience so I look like I have always been in IT?",
    a: "No. The line we publish is that we never fabricate employment, and that projects have to be genuine and safe to defend in a background check. If another institute offers to “adjust” your experience, treat that as a reason to leave the call.",
  },
  {
    q: "Does a career switch come with a job guarantee?",
    a: "No. Nobody can guarantee employment. The market decides, and so does your performance after six months of work. What is in writing is the process, the fee, and a 30-day money-back guarantee on registration. The placement fee is due only after you accept an offer. It is not due because you finished a switch.",
  },
  {
    q: "What do I pay while I am deciding?",
    a: "Nothing for the three free steps: counselling with a written report, the live masterclass, and the 7-hour Python bootcamp. Registration is ₹30,000 after that, or three EMIs of ₹10,000. Read the pay-after-placement page before you treat the second fee as “free until hired”. Registration is not contingent on an offer.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "Non-IT to IT", path: page.path },
] as const;

export const metadata: Metadata = moneyMetadata(page);

export default function NonItToItPage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode(crumbs),
    {
      "@type": "ItemList",
      name: "Live BrowseJobs programmes for a career switch",
      itemListElement: liveCourses.map((course, index) => ({
        "@type": "ListItem",
        position: index + 1,
        name: course.name,
        url: `https://browsejobs.ai/courses/${course.slug}`,
      })),
    },
    faqNode(faqs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="Career switch"
        title={page.title}
        crumbs={crumbs}
        primary={{ kind: "masterclass" }}
        secondary={{ href: "/courses", label: "See the four programmes" }}
        lede={
          <p>
            There is no one course that turns every non-IT career into an IT job. BrowseJobs teaches four live
            programmes: Data Engineering, Data Analytics, DevOps & Cloud, and Python Backend. Which one fits depends
            on whether you already work with numbers, systems, or code. A gap year is not, by itself, a no. Nobody
            can guarantee the switch ends in an offer. The market decides.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#choose", label: "Choose by background" },
          { href: "#tracks", label: "What each track is" },
          { href: "#gap", label: "Gap years and freshers" },
          { href: "#honest", label: "What we will not put on a CV" },
          { href: "#fees", label: "Pay before you are sure" },
          { href: "#check", label: "Check us before you resign" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="choose" kicker="A decision, not a funnel" heading="Start from the work you already do">
        <p>
          The useful question is not “which IT course is trending”. It is “which published syllabus sits next to the
          work I can already explain”. A mechanical engineer who lives in plant reports is closer to analytics than
          to Kubernetes. A sysadmin who already gets called when a deploy fails is closer to DevOps than to Power
          BI. Picking the course with the loudest advertisement is how people spend six months preparing for the
          wrong interview.
        </p>
        <p>
          The table is a reading aid. It is not an admission rule, and it is not a prediction. If two rows describe
          you, book the free counselling and take the written report. The homepage states that the report can
          recommend a path we do not teach. That sentence matters more than this table. A sales call that can only
          end in one of our four tracks is not counselling.
        </p>
        <div className="not-prose mt-6 overflow-x-auto rounded-[14px] border border-line">
          <table className="w-full min-w-[40rem] text-left text-sm">
            <caption className="sr-only">Which BrowseJobs programme fits a given background</caption>
            <thead className="bg-sky text-ink">
              <tr>
                <th scope="col" className="px-4 py-3 font-semibold">Your background</th>
                <th scope="col" className="px-4 py-3 font-semibold">Start here</th>
                <th scope="col" className="px-4 py-3 font-semibold">Why — not a promise</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.background} className="border-t border-line align-top">
                  <td className="px-4 py-4 text-ink2">{row.background}</td>
                  <th scope="row" className="px-4 py-4 font-semibold text-ink">
                    <TextLink href={row.href}>{row.start}</TextLink>
                  </th>
                  <td className="px-4 py-4 text-ink2">{row.why}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p>
          Python Backend is listed because the track is live on the site. The full brochure syllabus is not loaded
          for it yet — the course content file says so. [Krish: replace this row when the Python Backend module list
          is published. Do not paste an unsourced syllabus onto this page.] Until then, read{" "}
          <TextLink href="/courses/python-backend">the course page</TextLink> and ask on the counselling call what
          is actually being taught this month.
        </p>
      </MoneySection>

      <MoneySection id="tracks" kicker="The four live tracks" heading="What you would actually study">
        <p>
          <TextLink href="/courses/data-analytics">Data Analytics</TextLink> is six months. The published
          tools are Excel, SQL, Python, NumPy, Pandas, Power BI, Power Query, DAX, and statistics. The projects are
          dashboards: retail sales, HR attrition, e-commerce, financial performance, and an executive KPI view.
          Roles named on the page are Data Analyst, Business Analyst, Senior Data Analyst, and BI Analyst. This is
          the track for people who need to become fluent in questions a business already asks.
        </p>
        <p>
          <TextLink href="/courses/data-engineering">Data Engineering</TextLink> is six months and batch-first:
          Python, SQL, Spark, Databricks, AWS, Azure, and Airflow. It is the right conversation when analytics
          feels too small and you want to build the pipeline the dashboard sits on. It is the wrong conversation
          when you have never written a query and you hope the brand name will carry the first month. City-specific
          versions of this page are{" "}
          <TextLink href="/data-engineering-course-bangalore">Bengaluru / Whitefield</TextLink> and{" "}
          <TextLink href="/data-engineering-course-india">India, taught online</TextLink>.
        </p>
        <p>
          <TextLink href="/courses/devops-cloud">DevOps & Cloud</TextLink> is six months: Linux, Git, Docker,
          Kubernetes, Terraform, Ansible, Jenkins, AWS, and monitoring with the SRE module the brochure describes.
          Four projects are published, including a CI/CD pipeline and infrastructure as code. Take it if your
          current world is machines, releases, and uptime. Do not take it because “cloud” appeared in a reel.
        </p>
        <p>
          All four are rebuilt from interview questions rather than from a static outline that ignores the market.
          That is the method. It is not a claim that this month’s questions will be your questions, or that
          attending will make a company hire you. Agentic AI, Cyber Security, and ServiceNow are on the site as
          waitlist tracks. They are not live programmes. Do not plan a switch around a waitlist title.
        </p>
      </MoneySection>

      <MoneySection id="gap" kicker="Gaps and first jobs" heading="A gap year is a timeline problem, not a character flaw">
        <p>
          The course pages already carry three short cards for this moment. They are the words to use, because
          inventing a softer version would be a new claim:
        </p>
        <ul className="space-y-3">
          {situationCards.map((card) => (
            <li key={card.kicker} className="rounded-[14px] border border-line bg-white px-5 py-4">
              <p className="font-semibold text-ink">{card.kicker}</p>
              <p className="mt-1">{card.body}</p>
            </li>
          ))}
        </ul>
        <p>
          Read the fresher card carefully. “The equivalent of six months of hands-on experience” is how the site
          describes a free internship certificate. An interviewer can still ask what you owned in production. The
          honest answer is the projects and the internship work, not a company you did not join. A certificate does
          not convert into a notice period, a salary, or a team.
        </p>
        <p>
          A gap of a year, or of several years, is common on counselling calls. The useful work is to say what the
          gap was — family, health, a failed attempt, a job that was not IT — and then to put documented work after
          it. Hiding the gap with a title nobody can verify is how offers die in a background check, after you have
          already resigned. We will not help you do that. If your gap includes something you would rather explain
          once, in private, say it on the counselling call. The call is recorded and AI-monitored, which is the
          standard we publish for every counselling conversation.
        </p>
      </MoneySection>

      <MoneySection id="honest" kicker="The CV" heading="Switch domain. Do not invent a past.">
        <p>
          The fear under most non-IT searches is that the CV will be thrown out before a human reads it. Some
          institutes answer that fear by offering to rewrite history. Our published answer is the opposite. Projects
          you build are genuine work in the new domain. They can sit in the centre of the CV. They cannot be
          described as employment at a company that did not pay you.
        </p>
        <p>
          You should also expect the first interviews to be harder than a classmate’s who already has the title.
          Analytics interviews still ask SQL and a business case. Data engineering interviews still ask Spark and a
          pipeline you can draw. DevOps interviews still ask you what you would do when a deploy fails. The
          interview module on each live syllabus exists because the technical content is not sufficient on its own.
          Communication is scored. That will feel unfair if you were hoping the tool list was enough. It is the job.
        </p>
        <p>
          None of this is a reason to wait for a perfect background. It is a reason to choose the track whose
          interview you are willing to sit, and to keep the fee in writing before you resign from the job that
          currently pays you. Do not resign because a counsellor sounded certain. Certainty is the thing we refuse
          to sell.
        </p>
      </MoneySection>

      <MoneySection id="fees" kicker="Money" heading="Decide before the ₹30,000, not after">
        <p>
          The order is fixed. Written report, live masterclass, 7-hour Python bootcamp, then registration of
          ₹30,000 or three EMIs of ₹10,000. The placement fee — first three months of the CTC on an offer you
          accept, six EMIs, registration adjusted inside it — comes only after a yes from you to an employer. If
          the switch does not produce an offer you accept, that second fee is not charged. The registration is
          still a real payment, refundable for 30 days for any reason, and not refundable after that window.
        </p>
        <p>
          Read <TextLink href="/pay-after-placement">how pay after placement works</TextLink> before you repeat
          “I only pay if I get the job”. That sentence is true of the placement fee. It is not true of registration.
          Mixing them up is how people feel misled, and the wording is avoidable.
        </p>
      </MoneySection>

      <MoneySection id="check" kicker="Verify" heading="Check the market before you resign">
        <p>
          A career switch is expensive even when the first three steps are free, because your time is not free and
          your current salary is real. The site already publishes three checks. They take about fifteen minutes.
          Do them for Data Analytics, Data Engineering, and DevOps, and do them for any other institute you are
          considering. We are not exempt.
        </p>
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
          If the live job descriptions in your city do not mention the tools on a syllabus, do not start that
          syllabus because a page ranked for it. Bring the five descriptions to the counselling call. A written
          report that ignores them is a bad report. Ask for it to be redone, or walk away. The masterclass is still
          free if you only want to see how the syllabus is supposed to move when those descriptions move.
        </p>
      </MoneySection>

      <PromiseCards />
      <MoneyFaq faqs={faqs} />
      <RelatedLinks
        links={[
          { href: "/courses/data-analytics", label: "Data Analytics", note: "Excel, SQL, Python, Power BI. The usual first switch." },
          { href: "/courses/data-engineering", label: "Data Engineering", note: "Pipelines, Spark, Databricks, AWS, Azure, Airflow." },
          { href: "/courses/devops-cloud", label: "DevOps & Cloud", note: "Docker, Kubernetes, Terraform, Jenkins, AWS." },
          { href: "/courses/python-backend", label: "Python Backend", note: "Live track. Full module list not published yet." },
          { href: "/pay-after-placement", label: "Pay after placement", note: "What is due before an offer, and what is due after." },
        ]}
      />
      <ContactStrip />
    </MoneyArticle>
  );
}
