import { courseLandingNav } from "@/content/course-landings";

/**
 * Public SEO landing pages. `title` is the H1 unless `h1` is set.
 * Sitemap, footer, and the courses hub read this list so a new page
 * cannot ship without an internal link and a sitemap URL.
 */

export type SeoNavItem = {
  path: string;
  title: string;
  description: string;
  footerLabel: string;
  priority: number;
  h1?: string;
  documentTitle?: string;
  canonicalPath?: string;
  updated?: string;
  courseSchema?: boolean;
};
export const SEO_PAGES = {
  deBangalore: {
    path: "/data-engineering-course-bangalore",
    title: "Data Engineering Course in Bengaluru (Whitefield): Live, Pay After Placement",
    description:
      "A live six-month Data Engineering course in Whitefield, Bengaluru. SQL, Python, Spark, Databricks, AWS, Azure and Airflow. Placement fee only after you accept an offer.",
    footerLabel: "Data Engineering, Bengaluru",
    priority: 0.9,
    updated: "2026-10-14",
  },
  deIndia: {
    path: "/data-engineering-course-india",
    title: "Data Engineering Course in India: Live Online, Pay After Placement",
    description:
      "The same live six-month Data Engineering course, taught online across India, with counselling from the Whitefield, Bengaluru office. Placement fee only after an accepted offer.",
    footerLabel: "Data Engineering, India",
    priority: 0.85,
    updated: "2026-10-19",
  },
  nonIt: {
    path: "/non-it-to-it",
    title: "Non-IT to IT: Which Course Fits a Career Switch",
    description:
      "Which BrowseJobs course fits a move from non-IT into IT — analytics, data engineering, DevOps, or Python — including career gaps. A job is not guaranteed.",
    footerLabel: "Non-IT to IT",
    priority: 0.8,
  },
  payAfter: {
    path: "/pay-after-placement",
    title: "Pay After Placement: How the BrowseJobs Fee Works",
    description:
      "₹30,000 registration covers the course. The placement fee is three months of CTC, charged in EMIs only after you accept an offer.",
    footerLabel: "Pay after placement",
    priority: 0.8,
    updated: "2026-10-14",
  },
  aiHiring: {
    path: "/ai-hiring",
    title: "AI Hiring Platform for Teams in India",
    description:
      "AI hiring for teams: structure the JD, rank applicants, run a role-specific AI interview, then read a written brief. Free for six months. You still make the hire.",
    footerLabel: "AI hiring",
    priority: 0.8,
    updated: "2026-10-19",
  },
  secureInterview: {
    path: "/secure-interview",
    title: "Secure AI Interviews That Catch Cheating",
    h1: "The interview you can trust the answers to.",
    description:
      "An AI interview built to be the hardest to cheat: gaze, lip-sync and second-voice signals, with the evidence in your report. A human makes every decision.",
    footerLabel: "Secure interviews",
    priority: 0.8,
  },
  aiInterview: {
    path: "/ai-interview-platform",
    title: "AI Interview Platform for Hiring Teams",
    description:
      "Role-specific AI interviews, scored only after grading, then a written brief. Camera checks are not captured. Automation never releases an offer.",
    footerLabel: "AI interview platform",
    priority: 0.75,
    updated: "2026-10-19",
  },
  taurusAi: {
    path: "/taurusai",
    title: "Taurus AI: One Live Floor for Every AI Agent You Run",
    description:
      "See every AI agent you run as a robot at its desk: what it is doing, what is done, what it spent and what waits for your approval. Ask it out loud.",
    footerLabel: "Taurus AI",
    priority: 0.7,
  },
  taurusRecruitment: {
    path: "/taurusai/recruitment",
    title: "Taurus Hiring Floor: Watch Hiring Move Stage by Stage",
    description:
      "A live 3D view of your hiring pipeline: eight stages from applied to hired, and what each hiring bot is doing right now. Offers stay a human decision.",
    footerLabel: "Taurus hiring floor",
    priority: 0.7,
  },
} as const;

export type SeoPageKey = keyof typeof SEO_PAGES;

export const seoMoneyLinks: readonly SeoNavItem[] = [...Object.values(SEO_PAGES), ...courseLandingNav];
