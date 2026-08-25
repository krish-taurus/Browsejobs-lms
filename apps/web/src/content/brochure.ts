/**
 * Brochure-only content: the parts of a course PDF that are not already in
 * `courses.ts` (per-track career panel, the platform feature tour, and the
 * deployment story). Everything a brochure prints comes from here, from
 * `courses.ts` or from `landing.ts` — the generator invents nothing.
 *
 * Copy is drawn from the 2026 brochures and the Platform Spec. Do NOT add
 * claims here that are not already made on the site or in the spec: the
 * brand-voice rules in CLAUDE.md apply to print exactly as they do to the web.
 */

/**
 * The "why this career" panel on page 2. Each track argues its market from a
 * different angle, so the shape varies — `stats` for hard market numbers,
 * `ladder` for a salary progression, `flow` for a role progression.
 */
export type CareerPanel =
  | {
      kind: "stats";
      heading: string;
      body: string;
      note: string;
      stats: { value: string; label: string }[];
    }
  | {
      kind: "ladder";
      heading: string;
      body: string;
      label: string;
      note: string;
      rungs: { role: string; range: string }[];
    }
  | {
      kind: "flow";
      heading: string;
      body: string;
      label: string;
      note?: string;
      steps: { role: string; what: string }[];
    };

export const careerPanels: Record<string, CareerPanel> = {
  "data-engineering": {
    kind: "stats",
    heading: "Why Data Engineering is the hottest job of the 21st century",
    body: "Data is the new oil — and Data Engineers are the architects of this digital economy. From powering AI to enabling data-driven decisions, their impact is everywhere. The demand is skyrocketing; the opportunity is now.",
    note: "Market figures are industry estimates from public sources. Salary-hike figure is BrowseJobs internal data — not a promise of individual outcome.",
    stats: [
      { value: "1.1M", label: "global job postings for Data Engineers (2024)" },
      { value: "50%", label: "CAGR in the global data industry" },
      { value: "400K", label: "demand–supply gap in India & the US" },
      { value: "55%", label: "average salary hike for our graduates*" },
    ],
  },
  "devops-cloud": {
    kind: "ladder",
    heading: "Why DevOps is one of the highest-paying careers in tech",
    body: "DevOps skills are among the most in-demand in the industry — and the salary ladder reflects it. Automation-first, cloud-native, remote-friendly, with faster promotion cycles than almost any other engineering track.",
    label: "Salary growth ladder · Indian market ranges",
    note: "Salary ranges are market observations, not a promise. Packages depend on your profile, performance and the live market — verify current ranges yourself on Naukri (see Check 01).",
    rungs: [
      { role: "Freshers", range: "₹6 – 8 LPA" },
      { role: "Junior DevOps Engineer", range: "₹8 – 12 LPA" },
      { role: "DevOps Engineer", range: "₹12 – 18 LPA" },
      { role: "Senior DevOps Engineer", range: "₹18 – 22 LPA" },
      { role: "Cloud Architect", range: "₹20 – 25 LPA+" },
    ],
  },
  "data-analytics": {
    kind: "flow",
    heading: "Why Data Analytics is one of the fastest-growing careers",
    body: "In a world driven by data, analytics turns information into insights and insights into impact. From healthcare to finance, retail to tech — every industry needs data-driven professionals, and the career ladder runs long.",
    label: "Career growth flow",
    steps: [
      { role: "Fresher → Data Analyst", what: "Analyze data, uncover insights, create reports" },
      { role: "Business Analyst", what: "Convert insights into strategic solutions" },
      { role: "Senior Data Analyst", what: "Complex data, advanced models, key decisions" },
      { role: "Analytics Manager", what: "Lead teams, define strategy, deliver impact" },
      { role: "Data Analytics Lead", what: "Drive the vision, mentor professionals" },
    ],
  },
};

/**
 * The syllabus engine — the three steps printed on page 2 of every brochure.
 * Wording is fixed by the Platform Spec positioning line ("not written, it was
 * reverse-engineered"); do not paraphrase.
 */
export const syllabusEngine = {
  kicker: "The syllabus engine — rebuilt monthly",
  headline: "This syllabus was not written. It was reverse-engineered.",
  problem:
    "The problem with every other course: fixed syllabi written years ago, packed with topics no interviewer asks about — while the questions that decide your offer change every quarter. You spend months studying, then walk into interviews prepared for the wrong exam.",
  title: "Most institutes teach a syllabus.\nWe prepare you for the interview.",
  steps: [
    {
      step: "Step 01 — Listen",
      body: "Our Claude-based AI monitors up to 50 real & mock interviews every single day.",
    },
    {
      step: "Step 02 — Extract",
      body: "It collects the actual questions companies are asking for this role, this month.",
    },
    {
      step: "Step 03 — Rebuild",
      body: "The syllabus is rebuilt around real demand — you study only what's actually asked.",
    },
  ],
} as const;

/**
 * The platform tour — every tool a student gets on day one, grouped the way
 * the site's feature scenes group them. This is the page the 2026 brochures
 * were missing: they sold the syllabus and the placement engine, but never
 * the machine in between.
 */
export const platformFeatures = [
  {
    group: "Learn",
    items: [
      {
        name: "AI Tutor — awake at 2am",
        body: "Every doubt answered in seconds, in your context, from your syllabus. Students ask it hundreds of questions a course, because it never judges and never sleeps.",
      },
      {
        name: "Live batches + recordings",
        body: "Every class is live and instructor-led, then recorded and searchable by topic. Your access runs a full year, so a missed class is never a lost one.",
      },
      {
        name: "Coding Lab",
        body: "Browser-based labs with the real stack. You write, run and break things inside the LMS — no local setup standing between you and practice.",
      },
      {
        name: "Content Hub & lesson notes",
        body: "Per-lesson notes, slides and reference material, generated against the day's teaching plan and kept with the recording.",
      },
    ],
  },
  {
    group: "Prove",
    items: [
      {
        name: "Voice Mock Lab — the interviewer that calls you",
        body: "A voice AI rings your phone, runs a real technical round, and scores every answer on depth, communication and confidence. By the twelfth mock, the real thing feels like a rerun.",
      },
      {
        name: "Weekly technical + HR mocks",
        body: "Human-led mocks alongside the AI ones, AI-analysed afterwards, each returning a written gap report rather than a grade.",
      },
      {
        name: "PRI & the mastery map",
        body: "A Placement Readiness Index built from your mock data, attendance and assignments — with one clear next best action every day.",
      },
      {
        name: "Live interview question bank",
        body: "The questions pulled from monitored interviews for your role this month, organised round by round.",
      },
      {
        name: "Quizzes, assignments & graded feedback",
        body: "Scored work with trainer feedback, so readiness is a measurement rather than an opinion.",
      },
    ],
  },
  {
    group: "Get hired",
    items: [
      {
        name: "Job Radar",
        body: "Live roles from LinkedIn and Naukri matched to your skill graph and refreshed daily, so the openings are already waiting when you are ready.",
      },
      {
        name: "ATS resume, LinkedIn & GitHub",
        body: "Your CV rebuilt to survive the filter, your LinkedIn tuned for recruiter search, your GitHub arranged around the projects you actually shipped.",
      },
      {
        name: "Verifiable certificates",
        body: "Every certificate carries a QR code that resolves to a public verification page. An employer checks it in one scan.",
      },
      {
        name: "1:1 mentoring & support desk",
        body: "A mentor for the career decisions and a ticketed support desk for everything else, both inside the LMS.",
      },
    ],
  },
] as const;

/**
 * "How we deploy" — the part of the projects story the brochures skipped.
 * Projects are deployed to real infrastructure because a BGV-safe CV needs
 * work an interviewer can open, not a screenshot.
 */
export const deploymentStory = {
  kicker: "Ship it — deployment",
  headline: "Your projects don't stop at the notebook.",
  body: "Every CV-ready project in this program is deployed, not just coded. An interviewer can open it, and you can walk them through the pipeline that put it there — which is exactly the conversation that converts a project into an offer.",
  steps: [
    {
      step: "01",
      title: "Your own cloud account",
      body: "You build in a real AWS or Azure account with real IAM, real billing alarms and real limits — not a simulator.",
    },
    {
      step: "02",
      title: "Version-controlled from day one",
      body: "Git and GitHub from the first commit, with branches, pull requests and review — the workflow your first team will expect.",
    },
    {
      step: "03",
      title: "Automated build & deploy",
      body: "A pipeline takes each project from commit to running environment, so deployment is a repeatable step rather than a one-off.",
    },
    {
      step: "04",
      title: "Monitored and demoable",
      body: "Dashboards, logs and alerts on top, plus a written architecture walkthrough you rehearse in mock interviews.",
    },
  ],
} as const;

/** Page 3 pull quote — the founder's line on how readiness is decided. */
export const founderQuote = {
  quote:
    "We qualify you on data points, not emotions. Your mock-interview data decides when you're ready — and then the market meets you.",
  attribution: "— Dr. Krish Bhargav, Founder & Mentor",
} as const;

/** The four real, published testimonials printed on the closing page. */
export const brochureTestimonials = [
  {
    track: "DevOps",
    author: "KR Sampritha",
    body: "Big thanks to Trainer Krish for being such a motivating and knowledgeable guide. Your way of teaching with real-life examples made everything so easy to understand. You're not just a trainer, but a true mentor!",
  },
  {
    track: "Data Engineering",
    author: "Anish Lakumarapu",
    body: "The course is super well-structured, covering everything from ETL processes to data storage tools. Instead of just theory, I got to work on actual data pipelines and industry-relevant case studies.",
  },
  {
    track: "Data Engineering",
    author: "Saket Vaibhav",
    body: "I would like to sincerely thank BrowseJobs for their incredible support in helping me find a job. Krish Sir, you are doing a wonderful job in helping students who dream of working in the IT industry.",
  },
  {
    track: "DevOps",
    author: "Arbaz Khan",
    body: "I recommend BrowseJobs to anyone looking to advance their career in IT. The course was comprehensive, well-structured, and tailored. My tutor was always available to clear any doubts.",
  },
] as const;

/** Registered-entity line printed in the brochure colophon. */
export const entityLine =
  "BrowseJobs is a unit of IBrowseJobs Technologies Pvt Ltd · Fl 3, Channasandra Main Rd, Ambedkar Nagar, Whitefield, Bengaluru, Karnataka 560066 · Mon–Sat, 9:00 AM – 7:00 PM IST.";
