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
  { href: "#how", label: "How it works" },
  { href: "#for-employers", label: "Hire" },
  { href: "#gaps", label: "If you miss" },
  { href: "#courses", label: "Courses" },
  { href: "#fees", label: "Fees" },
  { href: "/jobs", label: "Jobs" },
] as const;

/**
 * Questions that sit on the homepage next to the existing fee FAQ.
 * Honest about what is live. No extra rates.
 */
export const recruiterFaqs = [
  {
    q: "What is the free AI interview?",
    a: "Fifteen questions drawn from your CV, scored out of 100. A score of 75% or more counts as clear. That score is what we put in front of HR. It is not a promise of a job.",
  },
  {
    q: "How does an employer hire?",
    a: "They tell the BrowseJobs AI Recruiter the role, by typing or by voice. The floor shows the job, the shortlist from the BrowseJobs pool and the client's own files, then the AI interview, L1, L2, an optional human round, pre-BGV, the offer, and joining. AI calls, background checks, offers, and joining chats are a demo. They are not live yet.",
  },
  {
    q: "Is the hiring floor live data?",
    a: "No. The names, calls, and scores on the floor are demo data. Nothing is sent to a candidate. Jobs, the pipeline, and the team pages stay as they are until this desk is wired to them.",
  },
] as const;

/**
 * Krish's claim, rendered once in the steps section with the shared disclaimer.
 * Do not paraphrase the 500% figure or add a second rate.
 */
export const CLEAR_PICKUP =
  "If you clear the AI interview (75% or more), your CV is 500% more likely to get picked. You are pre-qualified for the interview.";

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
    body: "A score of 75% or more counts as clear. We put you in front of HR with that score. Below that, free counselling shows what's blocking you. A course comes only if you need it to close that gap.",
  },
] as const;

export type ScreenMode = (typeof screenSteps)[number]["mode"];

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
