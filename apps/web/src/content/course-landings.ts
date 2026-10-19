import { publishedAddressLine } from "@/content/entity";
import { OFFICE_PHONE } from "@/content/phones";
import type { FaqItem } from "@/lib/seo";

/** Date these pages were written. Sitemap lastmod uses this and nothing later. */
export const COURSE_LANDING_UPDATED = "2026-10-14";

export type LandingMonth = { month: string; title: string; body: string };
export type LandingLink = { href: string; label: string; note: string };
export type LandingSection = { id: string; kicker: string; heading: string; paragraphs: readonly string[] };

export type CourseLanding = {
  id: string;
  path: string;
  /** Document title, including the brand, at most 60 characters. */
  title: string;
  h1: string;
  description: string;
  /** Self canonical when omitted. Bangalore Gen AI points at /courses/gen-ai. */
  canonicalPath?: string;
  /**
   * Show the Google Business Profile rating in the page copy.
   * It is not copied into Course.aggregateRating: Google does not allow a
   * business to mark up its own listing, or a rating taken from another site.
   */
  showGoogleRating?: boolean;
  kicker: string;
  updated: string;
  /** 40–60 word answer box. */
  answer: string;
  footerLabel: string;
  priority: number;
  courseSchema: boolean;
  schema: {
    idPath: string;
    name: string;
    description: string;
    urlPath: string;
    teaches: readonly string[];
  };
  facts: readonly { label: string; value: string }[];
  months: readonly LandingMonth[];
  sections: readonly LandingSection[];
  faqs: readonly FaqItem[];
  related: readonly LandingLink[];
  crumbs: readonly { name: string; path: string }[];
};

const PHONE = OFFICE_PHONE.display;
const ADDRESS = publishedAddressLine;

const feeFacts = [
  { label: "Duration", value: "6 months" },
  { label: "Registration", value: "₹30,000, covers the course" },
  { label: "Pay after placement", value: "3 months of CTC, charged in EMIs after you accept an offer" },
  { label: "Address", value: ADDRESS },
  { label: "Phone", value: PHONE },
  { label: "Founder", value: "Dr Krish Bharggav" },
  { label: "Next step", value: "Free AI interview" },
] as const;

const AI_MONTHS: readonly LandingMonth[] = [
  {
    month: "Month 1",
    title: "Python",
    body: "Python is the first month because the rest of the course is written in it. You learn the language well enough to read a short program, change it, and explain what it did. This is not a survey of every Python library. It is the base the later months stand on.",
  },
  {
    month: "Month 2",
    title: "Python, continued",
    body: "The second month stays with Python. You practise the parts you need before a model is involved: organising a program, handling data you can inspect, and saying clearly what the code is for. Nothing is added here that Krish has not named.",
  },
  {
    month: "Month 3",
    title: "Large language models",
    body: "Large language models, the LLMs in the syllabus, are the third month. You learn what you are calling, what a prompt is asking the model to do, and how to judge an answer instead of pasting it. The month stays inside that topic.",
  },
  {
    month: "Month 4",
    title: "Retrieval-augmented generation",
    body: "RAG is retrieval-augmented generation. The month is about grounding a model’s answer in documents you provide, so the answer can be checked against a source. You build that loop in Python and you can explain where the text came from.",
  },
  {
    month: "Month 5",
    title: "AI agents",
    body: "An AI agent, in this course, is a program that uses a model to take more than one step toward a goal. You build a small agent and you can say what it is allowed to do. The month does not add tools beyond Python, LLMs, RAG and agents.",
  },
  {
    month: "Month 6",
    title: "Project and the AI interview",
    body: "The last month is a project that uses the four named parts together, plus the free AI interview. The project is work you can walk through because you built it. It is not a job you did not have, and it is not placed on a CV as employment.",
  },
];

const DA_MONTHS: readonly LandingMonth[] = [
  {
    month: "Month 1",
    title: "Excel",
    body: "Excel is the first month, matching the published Data Analytics syllabus. Lookup, logic, pivot tables, cleaning, and a dashboard you can explain. Analyst interviews still open here, so the month does not skip it.",
  },
  {
    month: "Month 2",
    title: "SQL",
    body: "SQL is the second month: filters, joins, grouping, and the queries an analyst is asked to read out loud. The published module also includes window functions. You practise on data you can show.",
  },
  {
    month: "Month 3",
    title: "Python",
    body: "Python for analysis is the third month. The published tools are Python, Pandas and NumPy: cleaning, exploring, and a chart that supports a sentence. This is analysis Python, not a backend course.",
  },
  {
    month: "Month 4",
    title: "Statistics",
    body: "Statistics sits in the published syllabus between Python and Power BI: averages, spread, correlation, and a regression you can describe without hiding the assumption. You use it to defend a number, not to decorate a slide.",
  },
  {
    month: "Month 5",
    title: "Power BI",
    body: "Power BI is the fifth month: a model, DAX measures, a dashboard, and a refresh you can explain. The published projects start from this skill: retail sales, HR attrition, e-commerce, finance, and an executive KPI view.",
  },
  {
    month: "Month 6",
    title: "Projects and the AI interview",
    body: "The last month is those published projects, plus the free AI interview. Five dashboards are listed on the Data Analytics course page. You talk through the ones you built. A dashboard is not a year of employment.",
  },
];

function aiFaqs(where: string): readonly FaqItem[] {
  return [
    {
      q: `What is the best Agentic AI course in ${where}?`,
      a: `There is no honest ranking, and this page will not invent one. Check whether the classes are live, whether the syllabus matches work you can see in job descriptions, and whether the fee is in writing. BrowseJobs teaches Python, LLMs, RAG and AI agents over six months, live online and at the Whitefield centre. Registration is ₹30,000 and covers the course.`,
    },
    {
      q: "Is this different from a Gen AI course?",
      a: "The Gen AI course has its own page at /courses/gen-ai. The published modules on both pages are Python, LLMs, RAG and AI agents. The Bangalore Gen AI URL canonicalises to /courses/gen-ai so those two addresses do not compete. This page stays the Agentic AI course.",
    },
    {
      q: "Do I need to know coding already?",
      a: "You need to be willing to learn Python, because the course starts there. You do not need a prior job in software. If you have never written a program, say so in counselling. The written Career Analysis Report can recommend a slower start, including a path we do not teach.",
    },
    {
      q: "How long is it, and what is the fee?",
      a: "Six months. Registration is ₹30,000 and it covers the course. Pay after placement is separate: three months of the CTC on the offer you accept, charged in EMIs only after you accept. If you do not accept an offer, that second fee is not charged.",
    },
    {
      q: "Is there placement support?",
      a: "Yes, as a process, not as a promise of a job. Take the free AI interview. A score of 75% or above sends your CV to 3,000 HR recruiters and, on BrowseJobs internal data, comes with an almost 60% higher chance of an interview call. Below 75% you get a free counselling roadmap. A person always releases the offer letter. Nobody can guarantee employment.",
    },
    {
      q: "Is it online or offline?",
      a: `Both. The course is live online, and it is taught at the Whitefield classroom: ${ADDRESS}. You can attend in the room or online. Recordings are kept so a missed class can be caught up. The phone is ${PHONE}, Monday to Saturday, 9:00 AM to 7:00 PM IST.`,
    },
    {
      q: "What projects do I build?",
      a: "Work that uses the four named parts: Python, a large language model, retrieval-augmented generation, and an AI agent. You should be able to explain each piece because you wrote it. We will not put a company on your CV that did not employ you.",
    },
  ];
}

function daFaqs(where: string): readonly FaqItem[] {
  return [
    {
      q: `What is the best data analyst course in ${where} with placement?`,
      a: "There is no published ranking, so this page will not name a number one. Ask whether teaching is live, whether Excel, SQL, Power BI and Python are actually on the syllabus, and whether placement terms are in writing. BrowseJobs publishes that syllabus over six months. A job is not guaranteed.",
    },
    {
      q: "Is it pay after placement?",
      a: "Registration is not delayed until a job. ₹30,000 covers the course. Pay after placement is the second fee: three months of the CTC on the offer you accept, charged in EMIs only after you accept. No offer, no placement fee.",
    },
    {
      q: "Can non-IT graduates join?",
      a: "Yes. The course is written for freshers and for people moving from non-IT work. Book free counselling and read the written Career Analysis Report before you pay. The report is allowed to say this course is the wrong one.",
    },
    {
      q: "How long is the data analytics course?",
      a: "Six months. That is the length of the teaching. It is not a date by which you will be hired.",
    },
    {
      q: "What is the fee?",
      a: "₹30,000. That registration covers the course. The placement fee, if you accept an offer, is three months of that offer’s CTC, charged in EMIs afterwards.",
    },
    {
      q: "Which tools are covered?",
      a: "The published Data Analytics syllabus covers Excel, SQL, Python, statistics and Power BI, including Pandas, NumPy, Power Query and DAX. Kafka, Spark and cloud pipelines are the Data Engineering course, not this one.",
    },
    {
      q: "Online or a Whitefield classroom?",
      a: `Both. Live online, and a classroom at ${ADDRESS}. The phone is ${PHONE}. Hours are Monday to Saturday, 9:00 AM to 7:00 PM IST.`,
    },
  ];
}

const GEN_UPDATED = "2026-10-19";

function genFaqs(where: string): readonly FaqItem[] {
  return [
    {
      q: "Is the Gen AI course pay after placement?",
      a: "Registration is ₹30,000 and it covers the course. Pay after placement is the second fee: three months of the CTC on the offer you accept, charged in EMIs only after you accept. If you do not accept an offer, that second fee is not charged.",
    },
    {
      q: `Who is the Gen AI course in ${where} for?`,
      a: "People who can study for six months and want Python, large language models, RAG and AI agents. Freshers can start. Take the free AI interview and read the written Career Analysis Report before you pay. The report can say this course is the wrong one.",
    },
    {
      q: "Can non-IT switchers join?",
      a: "Yes. The course starts with Python. You do not need a prior software job. Read the non-IT page and book free counselling first. A switch is allowed to end in a different course, or in no course.",
    },
    {
      q: "How long is the Gen AI course?",
      a: "Six months. That is the length of the teaching. It is not a date by which you will be hired.",
    },
    {
      q: "Is there placement support?",
      a: "Yes, as a process, not as a promise of a job. Score 75% or above on the free AI interview and your CV goes to 3,000 HR recruiters. On BrowseJobs internal data, that score comes with an almost 60% higher chance of an interview call. Below 75% you get a free counselling roadmap. A person always releases the offer letter. Nobody can guarantee employment.",
    },
    {
      q: "Is it online or in Bangalore?",
      a: `Both. Live online, and a classroom at ${ADDRESS}. The phone is ${PHONE}, Monday to Saturday, 9:00 AM to 7:00 PM IST. Founder: Dr Krish Bharggav.`,
    },
  ];
}

const placementSection = (track: string): LandingSection => ({
  id: "placement",
  kicker: "Placement",
  heading: "How placement works, and where it stops",
  paragraphs: [
    `The next step on ${track} is the free AI interview at /get-hired, not a seat you pay for first. The clear mark is 75%. Score 75% or above and your CV goes to 3,000 HR recruiters. On BrowseJobs internal data, that score comes with an almost 60% higher chance of an interview call. Below 75%, you get a free counselling roadmap rather than a profile sent out in hope.`,
    "A person always releases the offer letter. The interview can rank and explain. It does not hire you, and neither does finishing six months of class. Nobody can guarantee employment. The market decides, and so does your performance. There is no fixed salary on this page.",
  ],
});

const chooseSection: LandingSection = {
  id: "choose",
  kicker: "How to choose",
  heading: "How to choose a course, without a ranking",
  paragraphs: [
    "Compare three things you can check this week. First, live or recorded: a person teaching a class you can attend is different from a shelf of old videos sold as a batch. Ask which one you are buying. BrowseJobs classes are live, with recordings kept so a missed session is recoverable.",
    "Second, the fee model. Write down what is due before anyone hires you, and what is due only after you accept an offer. Here, ₹30,000 covers the course. Pay after placement is three months of the CTC on the offer you accept, charged in EMIs after that acceptance. A slogan that says the course is free until you are hired is not this model.",
    "Third, the placement terms. Ask what score sends a CV anywhere, what happens below that score, and who is allowed to release an offer. Our answers are the 75% mark, the free counselling roadmap below it, and a person on the offer letter. Do not take a guarantee from anyone, including us. We will not give one.",
  ],
};

function relatedAi(twin: LandingLink, answer: LandingLink): readonly LandingLink[] {
  return [
    { href: "/courses/agentic-ai-course", label: "Agentic AI course", note: "Python, LLMs, RAG and AI agents." },
    twin,
    answer,
    { href: "/pay-after-placement", label: "Pay after placement", note: "₹30,000 covers the course. The placement fee waits." },
    { href: "/non-it-to-it", label: "Non-IT to IT", note: "Freshers and people moving from non-IT roles." },
    { href: "/skills/python", label: "Python", note: "The language the AI course starts with." },
    { href: "/founder", label: "Dr Krish Bharggav", note: "Founder." },
    { href: "/get-hired", label: "Free AI interview", note: "The step before a course." },
    { href: "/free-python-bootcamp", label: "Free 5-day Python bootcamp", note: "19–23 Oct 2026. One hour each evening, online." },
  ];
}

function relatedDa(twin: LandingLink, answer: LandingLink): readonly LandingLink[] {
  return [
    { href: "/courses/data-analytics", label: "Data Analytics course", note: "The published syllabus and projects." },
    twin,
    answer,
    { href: "/pay-after-placement", label: "Pay after placement", note: "What is due before a job, and what is not." },
    { href: "/non-it-to-it", label: "Non-IT to IT", note: "Whether analytics is the right switch." },
    { href: "/salaries/data-analyst-bengaluru", label: "Data analyst salary in Bengaluru", note: "Illustrative bands, with the disclaimer." },
    { href: "/skills/python", label: "Python", note: "Analysis Python on the syllabus." },
    { href: "/skills/sql", label: "SQL", note: "The query skill analyst interviews test." },
    { href: "/skills/power-bi", label: "Power BI", note: "Dashboards from the published syllabus." },
    { href: "/founder", label: "Dr Krish Bharggav", note: "Founder." },
    { href: "/get-hired", label: "Free AI interview", note: "Score first. Then decide on a course." },
    { href: "/free-python-bootcamp", label: "Free 5-day Python bootcamp", note: "A free evening class before you enrol." },
  ];
}

export const courseLandings: readonly CourseLanding[] = [
  {
    id: "agentic-course",
    path: "/courses/agentic-ai-course",
    title: "Agentic AI Course (6 Months, ₹30,000) · BrowseJobs",
    h1: "Agentic AI Course: Build AI Agents in 6 Months",
    description:
      "Agentic AI, live in Whitefield, online: 6 months, ₹30,000, pay after placement. Score 75%+ on a free AI interview and your CV goes to 3,000 HR recruiters.",
    kicker: "Agentic AI · 6 months",
    updated: COURSE_LANDING_UPDATED,
    answer:
      "The BrowseJobs Agentic AI course is six months of live teaching in Python, large language models, retrieval-augmented generation and AI agents. You can attend online or at the Whitefield classroom. Registration is ₹30,000 and covers the course. Pay after placement is three months of CTC, charged in EMIs only after you accept an offer.",
    footerLabel: "Agentic AI course",
    priority: 0.9,
    courseSchema: true,
    schema: {
      idPath: "/courses/agentic-ai-course",
      name: "Agentic AI Course",
      description:
        "6-month course covering Python, LLMs, RAG and AI agents, live online and at the Whitefield, Bengaluru centre.",
      urlPath: "/courses/agentic-ai-course",
      teaches: ["Python", "LLMs", "RAG", "AI agents"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Live online and Whitefield classroom" },
    ],
    months: AI_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "The course",
        heading: "What this course is",
        paragraphs: [
          "This is the Agentic AI course: Python, large language models, retrieval-augmented generation, and AI agents. The Gen AI course page is /courses/gen-ai. It publishes those same four modules under the Gen AI name, for the Bangalore search. The two pages stay separate so each name has an address.",
          `Classes are live. One way to attend is online. The other is the classroom at ${ADDRESS}. The phone on this page is ${PHONE}. Founder: Dr Krish Bharggav.`,
          "It is for freshers and for people moving from non-IT roles who can give it six months. If your work today is spreadsheets and you want dashboards first, Data Analytics is the closer course. This one starts with Python and ends with an agent.",
        ],
      },
      placementSection("this course"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for",
        paragraphs: [
          "Take it if you want to build with models, not only talk about them, and you can start from Python. A fresher can start. So can someone leaving a non-IT job. Read the longer version of that choice on the non-IT page before you treat a course as the only path.",
          "Do not take it if you need a guarantee of a job or a salary. We will not write one. Do not take it if you want a recorded library sold as live teaching. The classes are live.",
        ],
      },
      chooseSection,
    ],
    faqs: aiFaqs("India"),
    related: relatedAi(
      { href: "/agentic-ai-course-bangalore", label: "Agentic AI in Bangalore", note: "The same course, Whitefield and online." },
      { href: "/answers/best-agentic-ai-course-in-india", label: "Best Agentic AI course in India", note: "How to choose, without a fake ranking." },
    ),
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Agentic AI", path: "/courses/agentic-ai-course" },
    ],
  },
  {
    id: "agentic-bangalore",
    path: "/agentic-ai-course-bangalore",
    title: "Agentic AI Course in Bangalore (Whitefield) · BrowseJobs",
    h1: "Agentic AI Course in Bangalore: Live, Whitefield + Online",
    description:
      "Agentic AI in Bangalore (Whitefield): 6 months, ₹30,000, pay after placement. Take a free AI interview; score 75%+ and your CV goes to 3,000 HR recruiters.",
    kicker: "Bangalore · Whitefield",
    updated: COURSE_LANDING_UPDATED,
    answer:
      "The Agentic AI course in Bangalore is the six-month BrowseJobs programme taught live at the Whitefield classroom and live online. It covers Python, LLMs, RAG and AI agents. Registration is ₹30,000 and covers the course. Pay after placement is three months of CTC, in EMIs, only after you accept an offer. A job is not guaranteed.",
    footerLabel: "Agentic AI, Bangalore",
    priority: 0.85,
    courseSchema: true,
    schema: {
      idPath: "/courses/agentic-ai-course",
      name: "Agentic AI Course",
      description:
        "6-month course covering Python, LLMs, RAG and AI agents, live online and at the Whitefield, Bengaluru centre.",
      urlPath: "/courses/agentic-ai-course",
      teaches: ["Python", "LLMs", "RAG", "AI agents"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Whitefield classroom and live online" },
    ],
    months: AI_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "Bangalore",
        heading: "Bangalore, Bengaluru, and Whitefield",
        paragraphs: [
          "People search for an Agentic AI course in Bangalore, in Bengaluru, and in Whitefield. This page is that course. Bengaluru is the city on our address. Bangalore is the name still used in most job searches. Whitefield is the neighbourhood of the classroom.",
          `The room is ${ADDRESS}. You do not have to sit there for every session. The same class is live online, and recordings cover a miss. Come in for counselling or for class. Call ${PHONE}, Monday to Saturday, 9:00 AM to 7:00 PM IST.`,
          "If you are outside Bengaluru, use the India page. The syllabus does not change with the city. The office for an in-person conversation stays in Whitefield.",
        ],
      },
      placementSection("the Bangalore course"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for in Bangalore",
        paragraphs: [
          "It fits a fresher in the city, and it fits someone leaving a non-IT role, if they can give the programme six months. The non-IT page is the honest version of that decision, including when the written report should point away from us.",
          "It is a weak fit if you want dashboards and Excel as the whole job. That is Data Analytics, also taught from Whitefield. It is a weak fit if you want a recorded course you never attend.",
        ],
      },
      chooseSection,
    ],
    faqs: aiFaqs("Bangalore"),
    related: relatedAi(
      { href: "/agentic-ai-course-india", label: "Agentic AI course in India", note: "The same six months, for people outside the city." },
      { href: "/answers/best-gen-ai-course-in-bangalore", label: "Best Gen AI course in Bangalore", note: "Gen AI on this site is this syllabus." },
    ),
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Agentic AI, Bangalore", path: "/agentic-ai-course-bangalore" },
    ],
  },
  {
    id: "agentic-india",
    path: "/agentic-ai-course-india",
    title: "Agentic AI Course in India, Live Online · BrowseJobs",
    h1: "Agentic AI Course in India: Live Online, 6 Months",
    description:
      "Agentic AI in India, live online: 6 months, ₹30,000, pay after placement. Take a free AI interview; score 75%+ and your CV goes to 3,000 HR recruiters.",
    kicker: "India · live online",
    updated: COURSE_LANDING_UPDATED,
    answer:
      "The Agentic AI course in India is six months, taught live online by BrowseJobs, covering Python, LLMs, RAG and AI agents. The classroom is in Whitefield, Bengaluru, if you want to attend in person. Registration is ₹30,000 and covers the course. The placement fee is three months of CTC, charged in EMIs only after an accepted offer.",
    footerLabel: "Agentic AI, India",
    priority: 0.85,
    courseSchema: true,
    schema: {
      idPath: "/courses/agentic-ai-course",
      name: "Agentic AI Course",
      description:
        "6-month course covering Python, LLMs, RAG and AI agents, live online and at the Whitefield, Bengaluru centre.",
      urlPath: "/courses/agentic-ai-course",
      teaches: ["Python", "LLMs", "RAG", "AI agents"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Live online across India, classroom in Whitefield" },
    ],
    months: AI_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "India",
        heading: "The same course, from any city",
        paragraphs: [
          "You do not need to live in Bengaluru to take the course. Classes are live online. The syllabus is Python, then LLMs, then RAG, then AI agents, across six months. A city does not get a different fee or a shorter programme.",
          `If you want the room, it is in Whitefield: ${ADDRESS}. Counselling can happen there or on a call. The phone is ${PHONE}. Hours are Monday to Saturday, 9:00 AM to 7:00 PM IST. Email is hello@browsejobs.ai.`,
          "The Bangalore page is the same course written for people searching the city. This page is for the rest of India. Both point at the course page for the syllabus.",
        ],
      },
      placementSection("the India course"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for",
        paragraphs: [
          "Freshers, and people moving from non-IT work, anywhere the live class time suits them. The non-IT page walks through that choice. A course is the step after the free AI interview if the score and the counselling report still say you need one.",
          "Skip it if you are looking for a three-month shortcut. The published length is six months. Skip it if a page promises a job in exchange for the fee. This one does not.",
        ],
      },
      chooseSection,
    ],
    faqs: aiFaqs("India"),
    related: relatedAi(
      { href: "/agentic-ai-course-bangalore", label: "Agentic AI in Bangalore", note: "Whitefield classroom and the same syllabus." },
      { href: "/answers/best-agentic-ai-course-in-india", label: "Best Agentic AI course in India", note: "The choice, without a ranking." },
    ),
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Agentic AI, India", path: "/agentic-ai-course-india" },
    ],
  },
  {
    id: "gen-course",
    path: "/courses/gen-ai",
    title: "Gen AI Bangalore · Pay After Placement",
    h1: "Gen AI Course in Bangalore with Pay After Placement",
    description:
      "Gen AI course in Bangalore, six months, Whitefield and online. ₹30,000 covers the course. Placement fee is three months of CTC, in EMIs after you accept.",
    kicker: "Gen AI · Bangalore",
    updated: GEN_UPDATED,
    answer:
      "The Gen AI course in Bangalore is six months, live in Whitefield and online. The published modules are Python, LLMs, RAG and AI agents. Registration is ₹30,000 and covers the course. Pay after placement is three months of the CTC on the offer you accept, charged in EMIs only after you accept. A job is not guaranteed.",
    footerLabel: "Gen AI course",
    priority: 0.9,
    courseSchema: true,
    showGoogleRating: true,
    schema: {
      idPath: "/courses/gen-ai",
      name: "Gen AI Course",
      description:
        "6-month Gen AI course in Bangalore covering Python, LLMs, RAG and AI agents, live online and at the Whitefield centre. Registration is ₹30,000. Pay after placement is three months of CTC, charged in EMIs after an accepted offer.",
      urlPath: "/courses/gen-ai",
      teaches: ["Python", "LLMs", "RAG", "AI agents"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Whitefield classroom and live online" },
    ],
    months: AI_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "The course",
        heading: "Gen AI in Bangalore, with the fee in writing",
        paragraphs: [
          "This is the Gen AI course page. Six months, in Bangalore: the Whitefield classroom and the same class live online. The published modules are Python, large language models, retrieval-augmented generation, and AI agents. A topic that is not in that list is not claimed here.",
          "Registration is ₹30,000 and it covers the course. Pay after placement is three months of the CTC on the offer you accept, charged in EMIs only after you accept. The Agentic AI course lists the same four modules under its own name at /courses/agentic-ai-course.",
          `The classroom is ${ADDRESS}. The phone is ${PHONE}. Founder: Dr Krish Bharggav. The Bangalore search URL /gen-ai-course-bangalore canonicalises here so the two addresses do not compete. The India page stays separate.`,
        ],
      },
      placementSection("this course"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for, including a non-IT switch",
        paragraphs: [
          "Freshers, and people moving from non-IT work, who can give it six months and can start with Python. The non-IT page is the written test of that fit. Take the free AI interview and the counselling report before you pay. The report is allowed to recommend a different course, or none.",
          "Skip it if you want Excel, SQL and Power BI first. That is Data Analytics. Skip it if a page promises a job in exchange for the fee. This one does not.",
        ],
      },
      chooseSection,
    ],
    faqs: genFaqs("Bangalore"),
    related: [
      { href: "/gen-ai-course-india", label: "Gen AI course in India", note: "The same course, for people outside the city." },
      { href: "/gen-ai-course-bangalore", label: "Gen AI in Bangalore", note: "This search URL canonicalises here." },
      { href: "/courses/agentic-ai-course", label: "Agentic AI course", note: "The same published modules, under that name." },
      { href: "/pay-after-placement", label: "Pay after placement", note: "₹30,000 covers the course. The placement fee waits." },
      { href: "/non-it-to-it", label: "Non-IT to IT", note: "Whether this course fits a switch." },
      { href: "/founder", label: "Dr Krish Bharggav", note: "Founder." },
      { href: "/get-hired", label: "Free AI interview", note: "The step before a course." },
      { href: "/reviews", label: "Reviews", note: "The Google listing, and the words we copied from it." },
    ],
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Gen AI", path: "/courses/gen-ai" },
    ],
  },
  {
    id: "gen-bangalore",
    path: "/gen-ai-course-bangalore",
    title: "Generative AI Course in Bangalore · BrowseJobs",
    h1: "Gen AI Course in Bangalore: LLMs, RAG and AI Agents",
    description:
      "Gen AI in Bangalore (Whitefield): 6 months, ₹30,000, pay after placement. Take a free AI interview; score 75%+ and your CV goes to 3,000 HR recruiters.",
    canonicalPath: "/courses/gen-ai",
    kicker: "Gen AI · Bangalore",
    updated: GEN_UPDATED,
    answer:
      "The Gen AI course in Bangalore lives at /courses/gen-ai. Six months, live in Whitefield and online: Python, LLMs, RAG and AI agents. Registration is ₹30,000 and covers the course. Pay after placement is three months of CTC, in EMIs, after you accept an offer. This URL canonicalises to that course page.",
    footerLabel: "Gen AI, Bangalore",
    priority: 0.8,
    courseSchema: true,
    schema: {
      idPath: "/courses/gen-ai",
      name: "Gen AI Course",
      description:
        "6-month Gen AI course in Bangalore covering Python, LLMs, RAG and AI agents, live online and at the Whitefield centre.",
      urlPath: "/courses/gen-ai",
      teaches: ["Python", "LLMs", "RAG", "AI agents"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Whitefield classroom and live online" },
      { label: "Course page", value: "/courses/gen-ai" },
    ],
    months: AI_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "One syllabus",
        heading: "This Bangalore URL points at the Gen AI course",
        paragraphs: [
          "Searchers say Gen AI and Generative AI. The course page for that search is /courses/gen-ai. The published modules are Python, large language models, retrieval-augmented generation, and AI agents, over six months.",
          "The canonical tag on this URL points at /courses/gen-ai. Use either address. They should not compete in search, because they are one Bangalore course page and one alias. The Agentic AI course is a separate URL that lists the same four modules.",
          `The classroom is in Whitefield, ${ADDRESS}. Online attendance is the same batch. Phone ${PHONE}. Founder Dr Krish Bharggav.`,
        ],
      },
      placementSection("this Gen AI page"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for",
        paragraphs: [
          "The same people as the Agentic AI course: freshers, and people moving from non-IT roles, who can study for six months. The non-IT page is the place to test that fit before you pay.",
          "If your goal is Excel, SQL and Power BI, you want Data Analytics in Bangalore, not this page. Gen AI on this site is the model course.",
        ],
      },
      chooseSection,
    ],
    faqs: genFaqs("Bangalore"),
    related: relatedAi(
      { href: "/courses/gen-ai", label: "Gen AI course", note: "Canonical page for this Bangalore course." },
      { href: "/gen-ai-course-india", label: "Gen AI course in India", note: "The same course, live online." },
    ),
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Gen AI, Bangalore", path: "/gen-ai-course-bangalore" },
    ],
  },
  {
    id: "gen-india",
    path: "/gen-ai-course-india",
    title: "Generative AI Course in India, Online · BrowseJobs",
    h1: "Gen AI Course in India: Live Online, 6 Months",
    description:
      "Gen AI in India, live online: 6 months, ₹30,000, pay after placement. Take a free AI interview; score 75%+ and your CV goes to 3,000 HR recruiters.",
    kicker: "Gen AI · India",
    updated: GEN_UPDATED,
    answer:
      "The Gen AI course in India is the six-month programme on /courses/gen-ai: Python, LLMs, RAG and AI agents, live online, with a Whitefield classroom if you want the room. Registration is ₹30,000 and covers the course. Pay after placement is three months of CTC, in EMIs, only after you accept an offer.",
    footerLabel: "Gen AI, India",
    priority: 0.8,
    courseSchema: true,
    schema: {
      idPath: "/courses/gen-ai",
      name: "Gen AI Course",
      description:
        "6-month Gen AI course covering Python, LLMs, RAG and AI agents, live online across India and at the Whitefield, Bengaluru centre.",
      urlPath: "/courses/gen-ai",
      teaches: ["Python", "LLMs", "RAG", "AI agents"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Live online, classroom in Whitefield if you want it" },
      { label: "Course page", value: "/courses/gen-ai" },
    ],
    months: AI_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "One syllabus",
        heading: "India-wide, with the Bangalore course page",
        paragraphs: [
          "This page is for a Gen AI course you can take from anywhere in India. Classes are live online. The Whitefield classroom is there if you want the room. The course record is /courses/gen-ai. This India URL stays self-canonical because the search is national, not the Bangalore query.",
          "The published modules are Python, LLMs, RAG and AI agents. Six months. ₹30,000 covers the course. Pay after placement is three months of CTC, charged in EMIs only after you accept an offer.",
          `Office: ${ADDRESS}. Phone ${PHONE}. The founder is Dr Krish Bharggav. The Bangalore alias /gen-ai-course-bangalore canonicalises to the course page.`,
        ],
      },
      placementSection("the India Gen AI page"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for",
        paragraphs: [
          "Freshers and career switchers who want the model course, not the analytics course. If you are unsure, take the free AI interview and the counselling report first. The non-IT page explains when a course is the wrong next step.",
          "The page is not a promise that generative AI hiring is certain. The market decides. What we put in writing is the syllabus, the fee, and the interview rule.",
        ],
      },
      chooseSection,
    ],
    faqs: genFaqs("India"),
    related: relatedAi(
      { href: "/courses/gen-ai", label: "Gen AI course", note: "The Bangalore course page for this programme." },
      { href: "/gen-ai-course-bangalore", label: "Gen AI in Bangalore", note: "Canonicalises to the course page." },
    ),
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Gen AI, India", path: "/gen-ai-course-india" },
    ],
  },
  {
    id: "da-bangalore",
    path: "/data-analytics-course-bangalore",
    title: "Data Analytics Bangalore · Pay After Placement",
    h1: "Data Analytics Course in Bangalore (Whitefield): 6 Months, Pay After Placement",
    description:
      "Data Analytics, Bangalore (Whitefield): 6 months, ₹30,000, pay after placement. Score 75%+ on a free AI interview and your CV goes to 3,000 HR recruiters.",
    kicker: "Data Analytics · Bangalore",
    updated: COURSE_LANDING_UPDATED,
    answer:
      "The Data Analytics course in Bangalore is six months, live at the Whitefield classroom and live online. It teaches Excel, SQL, Python, statistics and Power BI. Registration is ₹30,000 and covers the course. Pay after placement is three months of the CTC on the offer you accept, charged in EMIs only after you accept. A job is not guaranteed.",
    footerLabel: "Data Analytics, Bangalore",
    priority: 0.85,
    courseSchema: true,
    schema: {
      idPath: "/courses/data-analytics",
      name: "Data Analytics Course",
      description:
        "6-month Data Analytics course covering Excel, SQL, Python, statistics and Power BI, live online and at the Whitefield, Bengaluru centre. Registration is ₹30,000. Pay after placement is three months of CTC, charged in EMIs after an accepted offer.",
      urlPath: "/courses/data-analytics",
      teaches: ["Excel", "SQL", "Python", "Power BI"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Whitefield classroom and live online" },
      { label: "Tools", value: "Excel, SQL, Python, Power BI" },
    ],
    months: DA_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "Bangalore",
        heading: "Data analytics in Bangalore and Whitefield",
        paragraphs: [
          "This is the Data Analytics course for people searching Bangalore, Bengaluru, or Whitefield. The full module list and the five published dashboards live on the Data Analytics course page. This page answers the city query and uses that same syllabus. It does not add topics.",
          `The classroom is ${ADDRESS}. Classes are also live online, so you are not required in Whitefield every evening. The phone is ${PHONE}. Hours are Monday to Saturday, 9:00 AM to 7:00 PM IST.`,
          "Illustrative pay bands for a data analyst in Bengaluru are on the salary page. They are benchmarks with a disclaimer. They are not an offer from this course, and they are not a salary we will promise you.",
        ],
      },
      placementSection("Data Analytics in Bangalore"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for",
        paragraphs: [
          "Freshers, and people moving from non-IT roles whose work is closer to reports than to pipelines. Excel, SQL, Python and Power BI are the tools. If you already want Spark, Databricks and cloud pipelines, that is Data Engineering, not this course.",
          "The non-IT page is the longer test of fit, including a career gap. A gap is not a bar by itself. An invented job on a CV is not how we fill one.",
        ],
      },
      chooseSection,
    ],
    faqs: daFaqs("Bangalore"),
    related: relatedDa(
      { href: "/data-analytics-course-india", label: "Data Analytics course in India", note: "The same six months, live online." },
      { href: "/answers/best-data-analytics-course-in-bangalore", label: "Best data analytics course in Bangalore", note: "How to choose in the city." },
    ),
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Data Analytics, Bangalore", path: "/data-analytics-course-bangalore" },
    ],
  },
  {
    id: "da-india",
    path: "/data-analytics-course-india",
    title: "Data Analytics in India · Pay After Placement",
    h1: "Data Analytics Course in India: Live Online, Pay After Placement",
    description:
      "Data Analytics in India, live online: 6 months, ₹30,000, pay after placement. Take a free AI interview; score 75%+ and your CV goes to 3,000 HR recruiters.",
    kicker: "Data Analytics · India",
    updated: COURSE_LANDING_UPDATED,
    answer:
      "The Data Analytics course in India is six months, live online, with a Whitefield classroom. Excel, SQL, Python and Power BI are the published tools. Registration is ₹30,000 and covers the course. Pay after placement is three months of CTC, in EMIs, only after you accept an offer. A job is not guaranteed.",
    footerLabel: "Data Analytics, India",
    priority: 0.85,
    courseSchema: true,
    schema: {
      idPath: "/courses/data-analytics",
      name: "Data Analytics Course",
      description:
        "6-month Data Analytics course covering Excel, SQL, Python, statistics and Power BI, live online and at the Whitefield, Bengaluru centre. Registration is ₹30,000. Pay after placement is three months of CTC, charged in EMIs after an accepted offer.",
      urlPath: "/courses/data-analytics",
      teaches: ["Excel", "SQL", "Python", "Power BI"],
    },
    facts: [
      ...feeFacts,
      { label: "Mode", value: "Live online across India, classroom in Whitefield" },
      { label: "Tools", value: "Excel, SQL, Python, Power BI" },
    ],
    months: DA_MONTHS,
    sections: [
      {
        id: "where",
        kicker: "India",
        heading: "One analytics course for every city",
        paragraphs: [
          "The fee does not change with your PIN code. Six months, ₹30,000, live online. The Bangalore page is the same programme with the Whitefield classroom in the foreground. The course page holds the module list: Excel, SQL, Python, statistics, Power BI, and the five dashboards.",
          `You can study from any city in India. If you want the room, the address is ${ADDRESS}. The phone is ${PHONE}, Monday to Saturday, 9:00 AM to 7:00 PM IST.`,
          "A data analyst turns numbers into a decision. A data engineer builds the pipelines that move the data. If that second sentence is the job you want, read the Data Engineering course instead of stretching this one.",
        ],
      },
      placementSection("Data Analytics in India"),
      {
        id: "who",
        kicker: "Fit",
        heading: "Who it is for",
        paragraphs: [
          "Freshers, and people leaving non-IT work, who want the analyst tools rather than a software-engineering syllabus. The non-IT page compares the tracks. Take the free AI interview before you decide the course is required.",
          "Six months is the course. It is not a promise that you will be hired in six months. There is no published time-to-offer.",
        ],
      },
      chooseSection,
    ],
    faqs: daFaqs("India"),
    related: relatedDa(
      { href: "/data-analytics-course-bangalore", label: "Data Analytics in Bangalore", note: "Whitefield classroom, same syllabus." },
      { href: "/answers/best-data-analyst-course-in-india", label: "Best data analyst course in India", note: "How to choose, without a ranking." },
    ),
    crumbs: [
      { name: "Home", path: "/" },
      { name: "Courses", path: "/courses" },
      { name: "Data Analytics, India", path: "/data-analytics-course-india" },
    ],
  },
];

export function getCourseLanding(id: string): CourseLanding {
  const page = courseLandings.find((item) => item.id === id);
  if (!page) throw new Error(`Unknown course landing: ${id}`);
  return page;
}

export const courseLandingNav = courseLandings.map((page) => ({
  path: page.path,
  title: page.h1,
  description: page.description,
  footerLabel: page.footerLabel,
  priority: page.priority,
  h1: page.h1,
  documentTitle: page.title,
  canonicalPath: page.canonicalPath,
  updated: page.updated,
  courseSchema: page.courseSchema,
}));
