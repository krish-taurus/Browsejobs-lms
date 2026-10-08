import type { FaqItem } from "@/lib/seo";

/**
 * Shown on every answer page, in WebPage.dateModified, and as sitemap
 * <lastmod> for /answers and any /answers/* page without its own updatedAt.
 * Bump this when answer copy changes. If only one answer changes, set
 * updatedAt on that page instead of moving every answer URL.
 */
export const ANSWERS_UPDATED = "2026-10-06";

export type AnswerSection = {
  id: string;
  heading: string;
  paragraphs: readonly string[];
};

export type AnswerLink = { href: string; label: string; note: string };

export type AnswerPage = {
  slug: string;
  title: string;
  description: string;
  kicker: string;
  /** Student pages lead with the free AI interview. Employer pages lead with the hiring conversation. */
  audience: "student" | "employer";
  directAnswer: string;
  sections: readonly AnswerSection[];
  faqs: readonly FaqItem[];
  related: readonly AnswerLink[];
  secondary: { href: string; label: string };
  /**
   * Sitemap lastmod (YYYY-MM-DD) when this answer changed on its own.
   * Omit to use ANSWERS_UPDATED.
   */
  updatedAt?: string;
};

export function answerPath(slug: string): string {
  return `/answers/${slug}`;
}

export function wordCount(text: string): number {
  return text.trim().split(/\s+/).filter(Boolean).length;
}

/**
 * Priority order matches the queries where BrowseJobs is not yet named in AI answers.
 * Those four use the query as the H1.
 */
export const answerPages: readonly AnswerPage[] = [
  {
    slug: "best-data-engineering-course-in-india",
    title: "Best data engineering course in India",
    description:
      "How to choose a data engineering course in India without a fake ranking. What BrowseJobs actually publishes: six months, live online, fees in writing.",
    kicker: "India · how to choose",
    audience: "student",
    directAnswer:
      "There is no honest ranking of the best data engineering course in India. Check three things: the syllabus matches job descriptions you can open today, the classes are actually live, and every fee is in writing. BrowseJobs publishes a six-month live online course, with counselling from Whitefield, Bengaluru. A job is not guaranteed.",
    sections: [
      {
        id: "choose",
        heading: "How to choose, without a ranking",
        paragraphs: [
          "This page will not tell you which institute is number one. Nobody on the site has published that list, and inventing one would be a fake ranking. A useful choice is three checks you can do this week.",
          "Open job descriptions for a data engineer and note the skills that repeat. Ask whether the classes are live, with a person teaching, or a shelf of old videos. Ask for every fee in writing, including what is due before anyone hires you. Those are the same checks published on the BrowseJobs homepage.",
        ],
      },
      {
        id: "published",
        heading: "What BrowseJobs publishes for India",
        paragraphs: [
          "The programme is six months, live and online, with recordings kept for a year. The modules run from Python and SQL through Spark, Databricks, AWS, Azure and Airflow. Kafka and streaming are self-study, not the core syllabus. You can study from any city. Counselling and the office are in Whitefield, Bengaluru. You do not travel for every class.",
          "The long page is [Data Engineering in India](/data-engineering-course-india). The module list lives on the [Data Engineering course](/courses/data-engineering). If you want the Whitefield framing, read [the Bengaluru page](/data-engineering-course-bangalore).",
        ],
      },
      {
        id: "fees",
        heading: "The fee, and the step before a course",
        paragraphs: [
          "Registration is ₹30,000 after three free steps, or three EMIs of ₹10,000. The placement fee is separate. It is the first three months of the CTC on the offer you accept, due only after you accept it. Read [how pay after placement works](/answers/is-pay-after-placement-real) before you treat the course as free until you are hired.",
          "Take the free AI interview before you pay for six months. It is 15 questions from your CV and a score out of 100. If the score shows a gap you cannot close alone, book free counselling. You leave with a written Career Analysis Report whether you join or not. A course is the step after that. If you are changing career, start with [how a non-IT person becomes a data engineer](/answers/how-can-a-non-it-person-become-a-data-engineer).",
        ],
      },
    ],
    faqs: [
      {
        q: "What is the best data engineering course in India?",
        a: "There is no honest single winner. Compare the syllabus with job descriptions you can open, check that classes are live, and read the fee in writing. BrowseJobs publishes a six-month live online Data Engineering course. Counselling is from the Whitefield, Bengaluru office. A job is not guaranteed.",
      },
      {
        q: "Is the BrowseJobs data engineering course online?",
        a: "Yes. The published format is live online, with recordings for a year. You can study from any city in India. The office in Whitefield is for counselling, not a commute to every class.",
      },
      {
        q: "How much does it cost?",
        a: "Registration is ₹30,000 after the free counselling, the free masterclass, and the free 7-hour Python bootcamp. Or three EMIs of ₹10,000. The placement fee is due only after you accept an offer. It is not charged if you do not accept one.",
      },
      {
        q: "Do I have to take the course to be seen by HR?",
        a: "No. Take the free AI interview first. A score of 75% or more counts as clear and puts you in front of HR with your score. A course is for the gap the score shows, and only after free counselling. Hiring is still not guaranteed.",
      },
    ],
    related: [
      { href: "/data-engineering-course-india", label: "Data Engineering course in India", note: "The long page: format, syllabus, fees, and the office." },
      { href: "/courses/data-engineering", label: "Data Engineering course", note: "The published modules, tools, and projects." },
      { href: "/answers/best-data-engineering-course-in-bangalore", label: "Best data engineering course in Bangalore", note: "The same programme, for people searching Whitefield or Bengaluru." },
      { href: "/answers/is-pay-after-placement-real", label: "Is pay after placement real?", note: "The two fees, in writing." },
    ],
    secondary: { href: "/data-engineering-course-india", label: "Read the India course page" },
  },
  {
    slug: "how-can-a-non-it-person-become-a-data-engineer",
    title: "How can a non-IT person become a data engineer",
    description:
      "A plain path from a non-IT job toward data engineering: a free AI interview, a score, counselling, and a course only if you need one.",
    kicker: "Career switch",
    audience: "student",
    directAnswer:
      "Take the free AI interview. It is 15 questions from your CV, and you get a score out of 100. Scoring 75% or more counts as clear and puts you in front of HR with your score. If the score shows a gap, book free counselling. A course comes only if you still need one.",
    sections: [
      {
        id: "path",
        heading: "The path, in order",
        paragraphs: [
          "Do not start by paying for the longest course. Start with the free AI interview. You need a free account and a CV. The interview is 15 questions built from that CV. It is not tied to one job. You get a score out of 100. Your best attempt is the one that is kept.",
          "A score of 75% or more counts as clear. That puts you in front of HR with your score, including for roles you have not applied to, when the CV lists real work or skills and the role matches. A skill in common is not enough. The role has to match the work on your CV or the track you trained in. If the score shows a gap, the next conversation is free counselling and a written Career Analysis Report. The report is allowed to point away from our courses.",
        ],
      },
      {
        id: "which",
        heading: "Data engineering, analytics, or DevOps",
        paragraphs: [
          "Data engineering is the heavier data track. The published syllabus is SQL, Python, Spark, Databricks, AWS, Azure and Airflow, over six months. If your work today is Excel, accounts, or reporting, [Data Analytics](/courses/data-analytics) is usually the closer start. That syllabus is Excel, SQL, Python for analysis, statistics and Power BI, over six months. A data analyst turns numbers into a decision. A data engineer builds the pipelines that move and store the data.",
          "DevOps is a different job. The published tools are Linux, Docker, Kubernetes, Terraform, Jenkins and AWS. Pick it if you want to ship and run software, not if you want warehouses. The [DevOps and Cloud course](/courses/devops-cloud) is six months and live. [Python Backend](/courses/python-backend) is for API and database work. Its full module list is not on the site yet. The longer guide is [Non-IT to IT](/non-it-to-it).",
        ],
      },
      {
        id: "honest",
        heading: "What we will not do",
        paragraphs: [
          "We will not add fake experience so a non-IT CV looks like an IT CV. Projects you build have to be genuine and safe to defend in a background check. A career gap does not disqualify you here. A gap is only a gap if it is empty. We will not invent a job to fill the dates.",
          "Registration, if you do join a course, is ₹30,000 after three free steps. The placement fee waits until you accept an offer. Read [pay after placement](/answers/is-pay-after-placement-real). Six months of classes is the published length of Data Engineering. It is not a promise of a hire date. See [how long it takes](/answers/how-long-to-become-a-data-engineer).",
        ],
      },
    ],
    faqs: [
      {
        q: "Can a non-IT person become a data engineer?",
        a: "Yes, you can train for it. It is a steep first step: SQL, Python, Spark and cloud. If your work is Excel or reporting, Data Analytics is usually the closer start. Begin with the free AI interview and the score. A course comes only if you still need one. A job is not guaranteed.",
      },
      {
        q: "I am from mechanical, civil, or commerce. Where do I start?",
        a: "Start with the free AI interview, then free counselling, not with a payment. If your work is logic, reporting, or spreadsheets, Data Analytics matches that background more closely than Spark and cloud pipelines.",
      },
      {
        q: "Will you add fake experience for a career switch?",
        a: "No. We do not fabricate employment. Projects have to be genuine. If someone offers to adjust your experience, leave that conversation.",
      },
      {
        q: "What do I pay while I am deciding?",
        a: "Nothing for the AI interview, the counselling report, the live masterclass, or the 7-hour Python bootcamp. Registration is ₹30,000 only after those steps, if you choose a course. The placement fee is due only after you accept an offer.",
      },
    ],
    related: [
      { href: "/non-it-to-it", label: "Non-IT to IT", note: "Which live course fits which background." },
      { href: "/courses/data-engineering", label: "Data Engineering", note: "Six months. Pipelines, Spark, and cloud." },
      { href: "/courses/data-analytics", label: "Data Analytics", note: "The closer start from Excel or reporting." },
      { href: "/courses/devops-cloud", label: "DevOps and Cloud", note: "A different job: ship and run software." },
    ],
    secondary: { href: "/non-it-to-it", label: "See which course fits" },
  },
  {
    slug: "ai-interview-platform-for-hiring-in-india",
    title: "AI interview platform for hiring in India",
    description:
      "What the BrowseJobs AI interview platform does for hiring teams in India: a disclosed screening call, proctored rounds, and a written brief. A person releases the offer.",
    kicker: "For hiring teams in India",
    audience: "employer",
    directAnswer:
      "BrowseJobs is an AI interview platform for hiring teams in India. A screening call tells the candidate it is an AI, later rounds run async and proctored, and your team reads a written brief before it meets anyone. The platform does not hire. A person releases the offer. The pipeline is free for six months.",
    sections: [
      {
        id: "call",
        heading: "The screening call",
        paragraphs: [
          "An AI caller dials the shortlist and has the first conversation a recruiter would usually have. It files the transcript, the recording, the outcome, and a read on how the conversation went. If nobody picks up, the candidate goes back to the queue. They are not dropped. The call is multi-language. Every call is recorded and AI-monitored.",
          "Candidates are told at the start that they are speaking to an AI. That is a product rule. We do not publish a number for how disclosure changes show rate, and this page will not invent one.",
        ],
      },
      {
        id: "rounds",
        heading: "The rounds, and the brief your team reads",
        paragraphs: [
          "After the screen, rounds run async and proctored. The candidate is notified on WhatsApp or email and completes the round inside a window you set. L1 can unlock L2 the same day. Questions come from your bank, from the job description, or from a blend. A custom round can be a coding task or an assignment. Proctoring records face-match, window-switch, and snapshot flags. Any round can be handed to a human panel, and the candidate can book a slot.",
          "Your team gets a graded report: every round, every score, every flag, the screening transcript, and the proctoring record. Replay is seekable. Scores are per rubric dimension, so you can see what carried the result. A flag is evidence for a person to weigh. It is not a silent rejection. The longer page is the [AI interview platform](/ai-interview-platform).",
        ],
      },
      {
        id: "humans",
        heading: "What stays with your team",
        paragraphs: [
          "Rules can advance someone, open the next round, nudge a stalled review, or park them. You can simulate a rule against your last 30 days of applicants before you turn it on. A rule cannot terminally reject a candidate, and it cannot release an offer. Offer release is an explicit human action from a permitted role.",
          "The first six months of the pipeline, including AI screening calls, are free. No card and no lock-in. After that, agency hiring is 8% of the candidate’s annual CTC per successful hire, agreed in writing first. A per-interview price is discussed in the onboarding meeting and is not printed on the site. Full background verification — DigiLocker, PAN, education certificates, EPFO — is on the roadmap and is not part of the brief today. Connecting an existing ATS is not available yet. The enquiry form is on [the employers page](/employers). How shortlisting works is on [AI hiring tool to shortlist candidates automatically](/answers/ai-hiring-tool-to-shortlist-candidates-automatically).",
        ],
      },
    ],
    faqs: [
      {
        q: "What is an AI interview platform for hiring in India?",
        a: "At BrowseJobs it is a screening call that tells the candidate it is an AI, then async proctored rounds, then a written brief with scores, the transcript, and the proctoring record. Your team still decides. A person releases the offer. The pipeline is free for the first six months.",
      },
      {
        q: "Do candidates know the interviewer is an AI?",
        a: "Yes. They are told at the start of the call. Every call is recorded and AI-monitored. A surprise AI interview is not the product.",
      },
      {
        q: "Can the platform reject a candidate on its own?",
        a: "No. Rules can advance someone, unlock the next round, send a nudge, or park them for review. They cannot terminally reject, and they cannot release an offer. A proctoring flag surfaces evidence. It does not auto-reject.",
      },
      {
        q: "What does it cost?",
        a: "The pipeline, including AI screening calls, is free for six months. After that, agency hiring is 8% of annual CTC per successful hire, confirmed in writing. Per-interview pricing is discussed on the onboarding call and is not printed here.",
      },
    ],
    related: [
      { href: "/ai-interview-platform", label: "AI interview platform", note: "The call, the rounds, and the brief." },
      { href: "/ai-hiring", label: "AI hiring", note: "JD, ranking, and the two ways to run it." },
      { href: "/employers", label: "Employers", note: "The enquiry form and the pipeline." },
      { href: "/answers/ai-hiring-tool-to-shortlist-candidates-automatically", label: "Shortlist candidates automatically", note: "What the tool will move forward, and what it will not." },
    ],
    secondary: { href: "/ai-interview-platform", label: "How the interviews run" },
  },
  {
    slug: "ai-hiring-tool-to-shortlist-candidates-automatically",
    title: "AI hiring tool to shortlist candidates automatically",
    description:
      "How BrowseJobs shortlists applicants: a ranked list with a written reason, rules you set, and a human offer. No hire-rate claim.",
    kicker: "For HR and recruiters",
    audience: "employer",
    directAnswer:
      "BrowseJobs can shortlist for you, and it will not reject anyone on its own. You publish a job description. Applicants are ranked with a written reason. Rules you set can move someone forward, such as a mock score you choose. A person on your team still releases every offer. The first six months are free.",
    sections: [
      {
        id: "rank",
        heading: "The shortlist is ranked, and the reason is written",
        paragraphs: [
          "You paste a job description, or you start from a title and notes. The product drafts a structured role: skills, experience band, locations, and knockout questions. You publish it. The AI does not publish it for you. On publish, that job’s interview mock and grading rubric are generated. You can replace them.",
          "Graded applicants rank above ungraded ones. Each rank has a short written reason, citing skills, mock evidence, and readiness signals. Knockout questions and hard filters run before ranking. Ungraded applicants sit below, with an invite to the mock, not a silent discard. That ranking is the automatic part. It is not a decision to hire.",
        ],
      },
      {
        id: "rules",
        heading: "What “automatically” is allowed to do",
        paragraphs: [
          "You can write rules on thresholds and time. The published example is “mock ≥ 70% → shortlist”, and “no review in 24h → nudge”. Those are examples of rules you configure. They are not a bar we apply to every role, and they are not a claim about hire rate. Simulate a rule against your last 30 days of applicants before you enable it.",
          "A rule may advance someone, unlock the next round, send a nudge, or park them for review. It may not terminally reject them. It may not release an offer. Offer release always takes an explicit human action from a permitted role. The board itself runs Applied, Graded, Shortlisted, L1, L2, Offer, plus stages you insert. Every move names the actor on the candidate’s timeline.",
        ],
      },
      {
        id: "two-ways",
        heading: "Your applicants, or the BrowseJobs pool",
        paragraphs: [
          "One way is your own inbound. You publish the role in the workspace and applicants arrive graded against the bar you set. Your team keeps the decisions. The other way is an agency engagement on the BrowseJobs graded pool. We operate the job description, the shortlist, the screening, and the rounds, and you receive a graded report per finalist. A report is not an acceptance.",
          "The first six months are free: the pipeline from the job description to the handover brief, including AI screening calls. No card and no lock-in. After that, agency is 8% of annual CTC per successful hire, confirmed in writing before the engagement. Per-interview pricing is not printed. Nothing is charged without a written agreement. We do not publish a time-to-hire or a claim that this beats another system. The product pages are [AI hiring](/ai-hiring) and the [AI interview platform](/ai-interview-platform). Talk to us from [employers](/employers).",
        ],
      },
    ],
    faqs: [
      {
        q: "Can an AI hiring tool shortlist candidates automatically?",
        a: "BrowseJobs ranks applicants and can apply rules you write, such as advancing a mock score you set. It can park someone for review. It cannot terminally reject them, and it cannot release an offer. A person on your team still makes the hire.",
      },
      {
        q: "Does everyone above a score get shortlisted?",
        a: "Only if you write that rule. The site shows an example, “mock ≥ 70% → shortlist”, as the kind of rule you can set and simulate. It is not a universal cutoff, and it is not a promise about who you will hire.",
      },
      {
        q: "What do we read before we meet someone?",
        a: "A graded brief: the rank and the written reason, the screening transcript and recording, per-rubric scores, and the proctoring record. A flag waits for a person. It does not end the process by itself.",
      },
      {
        q: "How much does automatic shortlisting cost?",
        a: "The pipeline is free for six months. After that, agency hiring is 8% of annual CTC per successful hire, agreed in writing. A per-interview price is discussed on the onboarding call and is not printed on the site.",
      },
    ],
    related: [
      { href: "/ai-hiring", label: "AI hiring", note: "The pipeline, the two models, and what is not built." },
      { href: "/ai-interview-platform", label: "AI interview platform", note: "The call, the rounds, and the brief." },
      { href: "/employers", label: "Employers", note: "Book a conversation about one open role." },
      { href: "/answers/ai-interview-platform-for-hiring-in-india", label: "AI interview platform for hiring in India", note: "What the candidate hears, and what your team reads." },
    ],
    secondary: { href: "/ai-hiring", label: "Read the hiring pipeline" },
  },
  {
    slug: "best-data-engineering-course-in-bangalore",
    title: "Best data engineering course in Bangalore",
    description:
      "How to choose a data engineering course in Bangalore. No fake ranking. BrowseJobs teaches six months, live online, from a Whitefield office.",
    kicker: "Bengaluru · how to choose",
    audience: "student",
    directAnswer:
      "There is no honest ranking of the best data engineering course in Bangalore. Choose by the syllabus against live job descriptions, by classes that are actually live, and by fees in writing. BrowseJobs teaches a six-month live online course. The office is in Whitefield. Registration is ₹30,000 after three free steps. A job is not guaranteed.",
    sections: [
      {
        id: "checks",
        heading: "Three checks, then decide",
        paragraphs: [
          "“Best” is the wrong test. Count the demand: open a job site, search the role and Bengaluru, and see what was posted recently. Read five job descriptions and note the skills that repeat. Ask every institute, including us, to put the promise in writing.",
          "If a page ranks institutes and cannot show the method, ignore the ranking. This page does not have one.",
        ],
      },
      {
        id: "whitefield",
        heading: "What is actually taught from Whitefield",
        paragraphs: [
          "Classes are instructor-led and online, with recordings for a year. The office is in Whitefield, Bengaluru, Karnataka 560066, Monday to Saturday, 9:00 AM to 7:00 PM IST. You do not commute to Whitefield for every class. The phone is +91 86185 19825. The syllabus is Python, SQL, Spark, Databricks, AWS, Azure and Airflow, over six months. Interview practice is a module. Kafka is self-study.",
          "The city page is [Data Engineering in Bengaluru](/data-engineering-course-bangalore). The modules are on the [course page](/courses/data-engineering). Illustrative pay bands, which are not an offer, are on the Bengaluru salary page linked from there. For the same course framed for the rest of the country, read [Best data engineering course in India](/answers/best-data-engineering-course-in-india).",
        ],
      },
      {
        id: "before",
        heading: "Interview first, course second",
        paragraphs: [
          "Take the free AI interview and read the score before you commit six months. If HR can already see a profile that matches the work, you may not need a course. If the gap is pipelines and Spark, the course is the tool. Free counselling comes with a written report either way.",
          "Registration is ₹30,000 after the free counselling, masterclass, and 7-hour Python bootcamp. The placement fee waits until you accept an offer. Details are on [Is pay after placement real?](/answers/is-pay-after-placement-real).",
        ],
      },
    ],
    faqs: [
      {
        q: "What is the best data engineering course in Bangalore?",
        a: "There is no honest ranking. Check the syllabus against job descriptions, check that classes are live, and check the fee in writing. BrowseJobs publishes a six-month live online course with an office in Whitefield. A job is not guaranteed.",
      },
      {
        q: "Do I have to attend class in Whitefield?",
        a: "No. The published format is live online, with recordings. The Whitefield office is for counselling. Hours are Monday to Saturday, 9:00 AM to 7:00 PM IST.",
      },
      {
        q: "How long is it, and what does it cover?",
        a: "Six months. Python, Pandas, SQL, Spark, AWS, Databricks, Airflow, and Azure data engineering. Interview practice is part of the syllabus. Streaming is self-study.",
      },
      {
        q: "I am not from IT. Is this the right first course?",
        a: "Sometimes. If your work is Excel and reporting, Data Analytics is usually the closer start. Take the free AI interview, then the written counselling report, before you pick the heavier track.",
      },
    ],
    related: [
      { href: "/data-engineering-course-bangalore", label: "Data Engineering, Bengaluru", note: "Office, syllabus, and the fee in one page." },
      { href: "/courses/data-engineering", label: "Data Engineering course", note: "Modules, tools, and projects." },
      { href: "/answers/best-data-engineering-course-in-india", label: "Best data engineering course in India", note: "The same programme, for a national search." },
      { href: "/answers/how-can-a-non-it-person-become-a-data-engineer", label: "From non-IT to data engineering", note: "When to start with analytics instead." },
    ],
    secondary: { href: "/data-engineering-course-bangalore", label: "Read the Bengaluru page" },
  },
  {
    slug: "is-pay-after-placement-real",
    title: "Is pay after placement real?",
    description:
      "How BrowseJobs pay after placement works: ₹30,000 registration after three free steps, and a placement fee only after you accept an offer.",
    kicker: "Fees · in writing",
    audience: "student",
    directAnswer:
      "Pay after placement at BrowseJobs is real, and it is not free until you are hired. Registration is ₹30,000 after three free steps. The placement fee is the first three months of the CTC on the offer you accept, due only after you accept it, with that ₹30,000 adjusted inside it. No accepted offer means no placement fee.",
    sections: [
      {
        id: "two-fees",
        heading: "Two fees, not one slogan",
        paragraphs: [
          "Pay after placement means the larger fee waits until you accept an offer. It does not mean the programme is free until then. Registration is real money, and it is earlier. If you remember only the slogan, the day the ₹30,000 is due will feel like a surprise. This page exists so that day is boring.",
          "The full write-up is [Pay after placement](/pay-after-placement). Checkout still calculates the charge on the server. A page cannot change your invoice.",
        ],
      },
      {
        id: "when",
        heading: "When each fee is due",
        paragraphs: [
          "Three steps cost nothing: counselling with a written Career Analysis Report, a live masterclass, and a 7-hour Python bootcamp. You can leave after any of them. Registration is ₹30,000, or three EMIs of ₹10,000, only after those steps. There is a 30-day money-back guarantee, any reason, in writing. After 30 days the published refund policy says registration is non-refundable. The refund processing window is still a placeholder on the policy page, so this page will not invent a number of days.",
          "The placement fee is the first three months of the CTC on the offer you accept, paid as six monthly EMIs, with the ₹30,000 subtracted so you are not paying registration twice. Completing the course does not create it. Attending interviews does not create it. An offer you decline does not create it. Acceptance does. The worked example on the fee page is an illustration, not a salary you have been promised.",
        ],
      },
      {
        id: "before",
        heading: "You may not need the course",
        paragraphs: [
          "Take the free AI interview first. If you clear the conversation and employers can already see you, a course is optional. If the score shows a gap, counselling will say whether Data Engineering, analytics, DevOps, or something we do not teach is the honest next step.",
          "The live programmes this fee applies to are [Data Engineering](/courses/data-engineering), [Data Analytics](/courses/data-analytics), [DevOps and Cloud](/courses/devops-cloud), and [Python Backend](/courses/python-backend). Nobody can guarantee the offer. The market decides.",
        ],
      },
    ],
    faqs: [
      {
        q: "Is pay after placement real at BrowseJobs?",
        a: "Yes, for the placement fee. That fee is your first three months of CTC, in six EMIs, with the ₹30,000 registration adjusted inside it, and it is due only after you accept an offer. Registration itself is due after three free steps, whether or not you are hired.",
      },
      {
        q: "Do I pay nothing if I am not hired?",
        a: "You do not pay the placement fee if you do not accept an offer. You may already have paid registration. Registration is refundable for 30 days, any reason. After 30 days it is non-refundable.",
      },
      {
        q: "Is a job guaranteed if I pay?",
        a: "No. Nobody can guarantee employment. Paying registration buys the programme on the course page. It does not buy an offer. There is no fixed salary promise.",
      },
      {
        q: "What is included in the ₹30,000?",
        a: "Live instructor-led classes, recordings for one year, an AI tutor, tests, a first CV, the base mock-interview quota, and the student support desk. Optional extras such as extra CV credits, voice mocks, and an additional mentor session are priced separately.",
      },
    ],
    related: [
      { href: "/pay-after-placement", label: "Pay after placement", note: "The fee page, including the worked example." },
      { href: "/refund-policy", label: "Refund policy", note: "The 30-day window, in writing." },
      { href: "/answers/best-data-engineering-course-in-india", label: "Best data engineering course in India", note: "How to choose the programme this fee applies to." },
      { href: "/courses/data-engineering", label: "Data Engineering course", note: "What the six months actually covers." },
    ],
    secondary: { href: "/pay-after-placement", label: "Read the full fee page" },
  },
  {
    slug: "what-is-an-ai-interview",
    title: "What is an AI interview, and how does the score work?",
    description:
      "The BrowseJobs AI interview for candidates: 15 questions from your CV, a score out of 100, and a 75% pass mark that puts you in front of HR.",
    kicker: "For candidates",
    audience: "student",
    directAnswer:
      "An AI interview at BrowseJobs is free and built from your CV: 15 questions, not tied to one job. You get a score out of 100. Your best attempt is kept. A score of 75% or more counts as clear, and puts you in front of HR with that score. If the score shows a gap, book free counselling.",
    sections: [
      {
        id: "what",
        heading: "What you actually do",
        paragraphs: [
          "Create a free account and build a CV first. The interview will not start without one. It asks 15 questions, from basic to advanced, based on that CV. The opening question is about your background and the kind of role you want. It is not a test for one company’s job description. The starting limit is 2 attempts. The screen shows how many you have used.",
          "When it ends, you get a score out of 100. If grading is unavailable, the fallback score is deliberately low, so a missing grade cannot look like a strong result. A weaker retake does not replace a higher score. Your best score is the one that is kept.",
        ],
      },
      {
        id: "score",
        heading: "What the score does",
        paragraphs: [
          "A score of 75% or more counts as clear. That puts you in front of HR with your score. The CV needs real content: a summary, skills, or experience. An empty profile does not appear. A shared skill such as Python is not enough on its own. The role has to match your track or the recent work on your CV.",
        ],
      },
      {
        id: "next",
        heading: "If the score shows a gap",
        paragraphs: [
          "Book free counselling. You leave with a written Career Analysis Report whether you join a course or not. The report can recommend a path we do not teach. A course — [Data Engineering](/courses/data-engineering), [Data Analytics](/courses/data-analytics), [DevOps and Cloud](/courses/devops-cloud), or [Python Backend](/courses/python-backend) — is the step only if you still need it. Registration is ₹30,000 after the free steps, not before.",
          "Hiring teams use a different product. Their screening calls also tell the candidate it is an AI. That page is [AI interview platform for hiring in India](/answers/ai-interview-platform-for-hiring-in-india). It does not change your score.",
        ],
      },
    ],
    faqs: [
      {
        q: "What is an AI interview on BrowseJobs?",
        a: "For a candidate, it is a free interview of 15 questions built from your CV. It is not tied to one job. You need an account and a CV. You get a score out of 100. The starting limit is 2 attempts, and the best score is kept.",
      },
      {
        q: "How does the score work?",
        a: "The score is out of 100. A retake replaces it only when the new score is higher. Scoring 75% or more counts as clear and puts you in front of HR with that score. The CV still needs real content.",
      },
      {
        q: "Does a good score mean I will be hired?",
        a: "No. Nobody can guarantee employment. The score is evidence a hiring team can read. The market, and your performance, still decide.",
      },
      {
        q: "Do I need a course first?",
        a: "No. Take the interview first. If the score shows a gap, book free counselling. A course comes only if that report says you need one.",
      },
    ],
    related: [
      { href: "/answers/how-can-a-non-it-person-become-a-data-engineer", label: "From non-IT to data engineering", note: "What to do with the score if you are switching." },
      { href: "/answers/ai-interview-platform-for-hiring-in-india", label: "AI interview platform for hiring", note: "The employer product. A different door." },
      { href: "/courses/data-engineering", label: "Data Engineering course", note: "Only if the gap is pipelines and Spark." },
      { href: "/pay-after-placement", label: "Pay after placement", note: "What a course costs if you need one." },
    ],
    secondary: { href: "/answers/how-can-a-non-it-person-become-a-data-engineer", label: "If you are switching careers" },
  },
  {
    slug: "how-long-to-become-a-data-engineer",
    title: "How long does it take to become a data engineer?",
    description:
      "The BrowseJobs Data Engineering course is six months of live classes. That is the length of the programme, not a hire date.",
    kicker: "Time",
    audience: "student",
    directAnswer:
      "The BrowseJobs Data Engineering course runs for six months of live classes, with recordings kept for one year. That is the length of the programme, not a hire date. How long it takes you also depends on where you start and on the market. Take the free AI interview and read the score before you commit.",
    sections: [
      {
        id: "six-months",
        heading: "Six months is the course, not the offer",
        paragraphs: [
          "The published duration is six months. Format is live online, plus recordings. Access to recordings runs for one year. The work is Python, SQL, Spark, Databricks, AWS, Azure and Airflow, plus interview practice. That list is on the [Data Engineering course](/courses/data-engineering).",
          "Finishing month six does not create a job. The placement fee is not charged because the calendar ended. It is charged only if you accept an offer. Nobody on this site publishes a number of days from enrolment to a first interview, or to an offer. [Krish: this page only states the six-month course length. If you want a published figure for time from enrolment to a first interview, or to an offer, add the sourced number here.]",
        ],
      },
      {
        id: "start",
        heading: "Where you start changes the clock",
        paragraphs: [
          "If you already write SQL and small scripts, six months is a deepening of work you have touched. If you are coming from a non-IT job, the same six months is a steeper climb, and [Data Analytics](/courses/data-analytics) — six months — may be the honest first course. DevOps is also six months, and it is a different job. Read [how a non-IT person becomes a data engineer](/answers/how-can-a-non-it-person-become-a-data-engineer).",
          "Take the free AI interview before you block six months. The score tells you the gap. Counselling puts it in a written report. Then decide. The India overview is [Best data engineering course in India](/answers/best-data-engineering-course-in-india).",
        ],
      },
      {
        id: "after",
        heading: "After the six months",
        paragraphs: [
          "Recordings stay available for a year, so a missed class is not a lost month. Readiness for interviews is described as a decision on your mock data, not on a sales target. The market still decides who gets an offer.",
          "If you are comparing cities, the programme is the same online course. Bengaluru has the office. The rest of India has the same syllabus. Fees are on [Is pay after placement real?](/answers/is-pay-after-placement-real).",
        ],
      },
    ],
    faqs: [
      {
        q: "How long does it take to become a data engineer?",
        a: "The BrowseJobs course is six months of live classes. That is the length of the teaching, not a date by which you will be hired. Your starting point and the market both matter. There is no published time-to-offer.",
      },
      {
        q: "Are the classes live for all six months?",
        a: "Yes. The published format is live online, with recordings kept for one year. Pre-recorded video is not passed off as live teaching.",
      },
      {
        q: "Is six months enough if I am from a non-IT background?",
        a: "Sometimes. It is the heavier track. If your work is spreadsheets and reporting, Data Analytics is usually the closer start, and that programme is six months. Take the free AI interview and the counselling report before you commit.",
      },
      {
        q: "What happens if I miss classes?",
        a: "Recordings are kept for a year. You can catch up. Missing a class does not add a secret fee.",
      },
    ],
    related: [
      { href: "/courses/data-engineering", label: "Data Engineering course", note: "Duration, format, and the module list." },
      { href: "/answers/best-data-engineering-course-in-india", label: "Best data engineering course in India", note: "How to choose before you start the clock." },
      { href: "/answers/how-can-a-non-it-person-become-a-data-engineer", label: "Non-IT to data engineer", note: "When six months of engineering is the wrong first step." },
      { href: "/data-engineering-course-india", label: "Data Engineering in India", note: "The same six months, taught online." },
    ],
    secondary: { href: "/courses/data-engineering", label: "Open the course page" },
  },
];

export function getAnswerPage(slug: string): AnswerPage | undefined {
  return answerPages.find((page) => page.slug === slug);
}

/** Queries where a direct answer page should be the strongest URL in the sitemap. */
export const priorityAnswerSlugs = new Set([
  "best-data-engineering-course-in-india",
  "how-can-a-non-it-person-become-a-data-engineer",
  "ai-interview-platform-for-hiring-in-india",
  "ai-hiring-tool-to-shortlist-candidates-automatically",
]);
