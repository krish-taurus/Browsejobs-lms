/**
 * Homepage funnel copy. Free AI interview → score → clear (HR) or
 * counselling, then a course only if you need it.
 * Do not invent placement rates, salaries, or logos. Fee figures are the
 * published model in `fees` (landing.ts).
 */

import { courses } from "@/content/landing";
import { getCourseDetail } from "@/content/courses";

export const HOME_TITLE = "Free AI interview · BrowseJobs";

export const HOME_DESCRIPTION =
  "Take a free AI interview. Fifteen questions from your CV, scored out of 100. A score of 75% or more counts as clear and puts you in front of HR with your score. Counselling and a course come only if you still need them. The placement fee is due only after you accept an offer.";

/**
 * Portrait hoodie explainer (9:16). Null until a film is published.
 * Override at runtime with NEXT_PUBLIC_HOME_EXPLAINER_EMBED (YouTube or Vimeo).
 */
export const HOME_EXPLAINER_EMBED_URL: string | null = null;

const YOUTUBE_HOSTS = new Set(["youtube.com", "www.youtube.com", "youtube-nocookie.com", "www.youtube-nocookie.com", "youtu.be"]);
const VIMEO_HOSTS = new Set(["vimeo.com", "www.vimeo.com", "player.vimeo.com"]);

/** Accept a YouTube/Vimeo URL and return a privacy-friendly embed src, or null. */
export function safeExplainerEmbed(raw: string | null | undefined): string | null {
  if (!raw?.trim()) return null;
  let url: URL;
  try {
    url = new URL(raw.trim());
  } catch {
    return null;
  }
  if (url.protocol !== "https:") return null;

  if (YOUTUBE_HOSTS.has(url.hostname)) {
    if (url.hostname === "youtu.be") {
      const id = url.pathname.split("/").filter(Boolean)[0];
      return id ? `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}` : null;
    }
    const fromPath = url.pathname.startsWith("/embed/")
      ? url.pathname.slice("/embed/".length).split("/")[0]
      : null;
    const id = fromPath || url.searchParams.get("v");
    return id ? `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}` : null;
  }

  if (VIMEO_HOSTS.has(url.hostname)) {
    const id = url.pathname.split("/").filter(Boolean).find((part) => /^\d+$/.test(part));
    return id ? `https://player.vimeo.com/video/${id}` : null;
  }

  return null;
}

/** Short captions on the portrait frame. Four beats — the CSS cycle depends on that count. */
export const explainerBeats = [
  { n: "01", title: "You sit the interview." },
  { n: "02", title: "You get a score." },
  { n: "03", title: "Clear — HR sees it." },
  { n: "04", title: "Miss — you see the block." },
] as const;

export const homeNav = [
  { href: "#how", label: "Candidates" },
  { href: "#for-employers", label: "Hire" },
  { href: "#ai-recruiter", label: "Floor" },
  { href: "#stages", label: "Stages" },
  { href: "#faq", label: "Questions" },
  { href: "/jobs", label: "Jobs" },
] as const;

/**
 * Questions that sit on the homepage next to the existing fee FAQ.
 * Honest about what is live. No extra rates.
 */
export const recruiterFaqs = [
  {
    q: "What is the free AI interview?",
    a: "You answer about 15 questions based on your CV. The AI scores you out of 100. A score of 75% or more means you are pre-qualified. It is not a promise of a job.",
  },
  {
    q: "What does 75% mean?",
    a: "Clearing the AI interview (75% or more) increases your chance of getting an interview call by almost 60%. Score 75% and your CV is sent to 3,000 HR recruiters. Below 75%, you can book a free counselling session, follow a personal improvement plan, practise, and retake the interview.",
  },
  {
    q: "How does an employer hire?",
    a: "They share the role. BrowseJobs puts pre-qualified candidates in front of them, calls and screens the shortlist, runs L1 and L2, and prepares pre-BGV. The hiring floor shows each stage. A person must always release the offer letter. The names on the marketing floor are sample data.",
  },
  {
    q: "Is the hiring floor live data?",
    a: "The floor on these pages is sample data, with fictional names. It shows the desk an employer uses. Nothing on that sample is sent to a real candidate.",
  },
] as const;

/**
 * Owner claims. Render with the shared disclaimer immediately after.
 * Do not add a second rate.
 */
export const CLAIM_INTERVIEW_CALL =
  "Clearing the AI interview (75% or more) increases your chance of getting an interview call by almost 60%.";

export const CLAIM_RECRUITERS = "Score 75% and your CV is sent to 3,000 HR recruiters.";

export const SEVENTY_FIVE = [
  { title: "About 15 questions.", body: "They are based on your CV. You answer them. It is an interview, not a class." },
  { title: "The AI scores you.", body: "You see the score out of 100, and where the answers were thin." },
  { title: "75% or more.", body: "That score means you are pre-qualified." },
  { title: "Your CV goes out.", body: CLAIM_RECRUITERS },
  { title: "A better chance of a call.", body: CLAIM_INTERVIEW_CALL },
] as const;

export const BELOW_SEVENTY_FIVE = [
  { title: "Score under 75%.", body: "You see the score, and what blocked a clear." },
  { title: "Free counselling session.", body: "A BrowseJobs counsellor calls you back and walks through the result." },
  { title: "A personal improvement plan.", body: "A course only if you need it to close the gap." },
  { title: "Practise.", body: "You work the plan. The interview is still free to retake." },
  { title: "Retake the AI interview.", body: "Same kind of round. A new score." },
  { title: "Reach 75%.", body: "You are pre-qualified." },
  { title: "Your CV goes to 3,000 HRs.", body: "The same step as anyone else who clears." },
] as const;

/** Copy for the callback section. Edit this when the owner sets a count or a channel. */
export const COUNSELLING_COPY = {
  kicker: "Free",
  title: "Free counselling session.",
  body: "Book a free counselling session. A BrowseJobs counsellor calls you back. Tell us when to phone.",
  submit: "Request a callback",
} as const;

export const screenSteps = [
  {
    id: "step-interview",
    n: "01",
    mode: "interview" as const,
    title: "You take a free AI interview",
    body: "Same style as a real screening. You answer out loud or in text. It is an interview, not a class.",
  },
  {
    id: "step-score",
    n: "02",
    mode: "score" as const,
    title: "You get a clear score and feedback",
    body: "You see how deep your answers went, how clearly you spoke, and which skills showed up. The score is a read of this round. It is not a promise of a job.",
  },
  {
    id: "step-outcome",
    n: "03",
    mode: "outcome" as const,
    title: "Two ways it can go",
    body: "A score of 75% or more counts as clear. Your CV is sent to 3,000 HR recruiters. Below that, a free counselling session shows what's blocking you. A course comes only if you need it.",
  },
] as const;

export type ScreenMode = (typeof screenSteps)[number]["mode"];

/** One line per hiring stage. The service includes each of these. A person releases the offer. */
export const hiringStages = [
  {
    name: "AI interview",
    body: "About 15 questions from the CV, scored out of 100. A score of 75% or more counts as clear.",
  },
  {
    name: "Calls",
    body: "We call and screen shortlisted candidates.",
  },
  {
    name: "L1",
    body: "We run the first scored round after the AI interview.",
  },
  {
    name: "L2",
    body: "We run a second scored round.",
  },
  {
    name: "Human round",
    body: "Optional. Someone on the hiring team can meet them.",
  },
  {
    name: "Pre-BGV",
    body: "We run a background check before any letter.",
  },
  {
    name: "Offer",
    body: "Needs your approval. A person must always release the offer letter, even in autonomous mode.",
  },
  {
    name: "Joining",
    body: "We stay in touch until they join.",
  },
] as const;

/** Why a live course is suggested — only rendered when that course URL is live. */
const GAP_WHEN: Record<string, string> = {
  "data-engineering": "The interview points you here when Spark, SQL, or how you design a data pipeline doesn't hold.",
  "devops-cloud": "The interview points you here when containers, CI, or cloud work doesn't hold under questions.",
  "python-backend": "The interview points you here when APIs, data models, or production Python need work.",
  "data-analytics": "The interview points you here when SQL, dashboards, or explaining the data needs work.",
};

export type RecommendedCourse = {
  slug: string;
  code: string;
  name: string;
  tagline: string;
  when: string;
  href: string;
};

/** Live course pages only. A slug that 404s (agentic-ai and the other waitlists) is dropped. */
export function recommendedCourses(): RecommendedCourse[] {
  return courses.flatMap((course) => {
    if (!course.live) return [];
    const detail = getCourseDetail(course.slug);
    if (!detail?.live) return [];
    return [
      {
        slug: course.slug,
        code: course.code,
        name: course.name,
        tagline: course.tagline,
        when: GAP_WHEN[course.slug] ?? course.tagline,
        href: `/courses/${course.slug}`,
      },
    ];
  });
}
