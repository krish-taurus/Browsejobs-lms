/**
 * Landing content — Platform Spec v1.0 (docs/BrowseJobs_Platform_Spec_v1.pdf).
 * Copy here is drawn from the spec; do NOT invent claims. Static for now;
 * later phases move this into the CMS (PageContent).
 */

/**
 * MANDATORY next to every stat (spec §3.4). Stored once; render only via
 * the shared <Disclaimer/> component. Do not paraphrase.
 */
export const DISCLAIMER =
  "Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.";

export const FOOTER_LINE =
  "Every promise in writing · Every call recorded & AI-monitored.";

export const contact = {
  phone: "+91 86185 19825",
  email: "hello@browsejobs.ai",
  address: "Whitefield, Bengaluru, Karnataka 560066",
  hours: "Mon–Sat, 9:00 AM – 7:00 PM IST",
  entity: "IBrowseJobs Technologies Pvt Ltd",
} as const;

/**
 * Legal registration + DPDP details, in ONE place for the privacy / terms /
 * refund pages. Bracketed values are placeholders the founder replaces before
 * launch (legal review required); update them here and all three pages follow.
 */
export const legal = {
  cin: "[CIN]",
  gst: "[GST]",
  grievanceOfficer: {
    name: "[Grievance Officer name]",
    email: contact.email,
  },
  retention: {
    leads: "[retention window]",
    students: "[retention window]",
  },
  refundWindow: "[refund processing window]",
} as const;

/** Stat band — labels per the 2026 brochures. */
export const stats = [
  { value: 50, suffix: "/day", label: "Real interviews monitored by our AI" },
  { value: 90, prefix: "~", suffix: "%", label: "Of interview questions come from our material" },
  { value: 98, suffix: "%", label: "Of candidates who attend interviews get placed" },
  { value: 1, suffix: " year", label: "Unlimited live batches & recordings" },
] as const;

/** Aggregates from the 2026 brochures (used on /reviews). */
export const reviewAggregates = [
  { value: 5000, suffix: "+", label: "Learners trained & empowered" },
  { value: 4.9, suffix: "/5", decimals: 1, label: "Average rating, based on reviews" },
  { value: 400, suffix: "+", label: "Hiring partners connected" },
  { value: 2013, label: "London est. · India since 2020" },
] as const;

/** The funnel spine (spec §4): three free steps, then the paid rung. */
export const freeLadder = [
  {
    step: "01",
    free: true,
    title: "Free counselling + written Career Analysis Report",
    body: "Talk to us. You leave with a written report on where you stand and what to do — whether you join or not.",
  },
  {
    step: "02",
    free: true,
    title: "Free live Masterclass",
    body: "See the method live. How the syllabus is reverse-engineered from real interviews, and what you'd actually study.",
  },
  {
    step: "03",
    free: true,
    title: "Free 7-hour Python Bootcamp",
    body: "Sit a real teaching day before you decide. Seven hours of live Python — no card, no commitment.",
  },
  {
    step: "04",
    free: false,
    title: "Registration — ₹30,000",
    body: "Only after all three free steps. EMI available: 3 × ₹10,000. Then full enrolment and LMS access.",
  },
] as const;

export type CourseCard = {
  code: string;
  slug: string;
  name: string;
  tagline: string;
  live: boolean;
};

/** Spec §6.1: 4 live + 3 waitlist. */
export const courses: CourseCard[] = [
  { code: "DE", slug: "data-engineering", name: "Data Engineering", tagline: "Pipelines, warehouses, and the modern data stack.", live: true },
  { code: "DC", slug: "devops-cloud", name: "DevOps & Cloud", tagline: "Ship, scale, and run production systems.", live: true },
  { code: "PB", slug: "python-backend", name: "Python Backend", tagline: "APIs, databases, and production Python.", live: true },
  { code: "DA", slug: "data-analytics", name: "Data Analytics", tagline: "SQL, dashboards, and decisions from data.", live: true },
  { code: "AA", slug: "agentic-ai", name: "Agentic AI", tagline: "Build with LLMs, agents, and tools.", live: false },
  { code: "CS", slug: "cyber-security", name: "Cyber Security", tagline: "Defend real systems against real attacks.", live: false },
  { code: "SN", slug: "servicenow", name: "ServiceNow", tagline: "The enterprise workflow platform.", live: false },
];

/** "Don't trust us. Verify us." — the exact three checks from the brochures. */
export const verifyChecks = [
  {
    title: "Count the demand",
    time: "5 min",
    body: "Open Naukri → search the role + your city → filter “Last 7 days” → count the postings. If hiring is real, you'll see it in the number.",
  },
  {
    title: "Read 5 job descriptions",
    time: "5 min",
    body: "Note the skills that repeat. Then compare them against any institute's syllabus — ours included. A syllabus that doesn't match live JDs is preparing you for the wrong exam.",
  },
  {
    title: "Pressure-test everyone",
    time: "5 min",
    body: "Ask every institute: will you put every promise in writing? Is your success rate defined clearly? Do you guarantee a job? Compare the answers.",
  },
] as const;

/** Green card — what we promise in writing (spec §3.5/3.6). */
export const promisesKept = [
  "Every commitment you get from us is in writing.",
  "Three full steps are free before you pay a rupee.",
  "The placement fee is due only after you accept an offer.",
  "30-day money-back guarantee — any reason, full refund.",
  "Every counselling call is recorded & AI-monitored.",
] as const;

/** Red card — what we will never tell you (spec §3.3). */
export const promisesNever = [
  "“We guarantee you a job.” Nobody honestly can — the market decides.",
  "“We'll adjust your experience.” We never fabricate employment.",
  "A fixed salary promise.",
  "That hiring is certain, or that there's a shortcut.",
] as const;

/** Spec §3.6 — render exactly; real amounts are server-owned at checkout. */
export const fees = {
  registration: 30000,
  registrationEmi: { months: 3, amount: 10000 },
  guaranteeDays: 30,
  placement: {
    monthsOfCtc: 3,
    emis: 6,
    adjusted: 30000,
  },
} as const;

export const faqs = [
  {
    q: "What do I pay, and when?",
    a: "Nothing until you've finished the three free steps — counselling with a written report, the live masterclass, and the 7-hour Python bootcamp. Then registration is ₹30,000 (or 3 EMIs of ₹10,000). The placement fee — your first 3 months' CTC — is due only after you accept an offer, paid as 6 monthly EMIs from your new salary, with the ₹30,000 adjusted inside it.",
  },
  {
    q: "Do you guarantee a job?",
    a: "Nobody can guarantee employment — the market decides. What we put in writing: the process. A syllabus rebuilt monthly from real interviews, live teaching, mock interviews scored on data, and a placement team that works your profile until you're placed.",
  },
  {
    q: "Are classes actually live?",
    a: "Yes. Every batch is live and instructor-led, with recordings available afterward. We never pass pre-recorded video off as live teaching.",
  },
  {
    q: "What is the 30-day guarantee?",
    a: "A full refund within 30 days of registration, for any reason, in writing. No forms designed to make you give up.",
  },
  {
    q: "How is the syllabus different?",
    a: "It isn't written — it's reverse-engineered. An AI system monitors up to ~50 real and mock interviews a day, extracts what companies are actually asking this month, and the syllabus is rebuilt around that. You study only what's actually asked.",
  },
  {
    q: "What if I miss a class?",
    a: "Every class is recorded and searchable by topic, and your access runs a full year. You can always catch up.",
  },
] as const;
