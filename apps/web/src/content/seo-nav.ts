/**
 * Public SEO landing pages. Titles are the H1 and the document title.
 * Sitemap, footer, and the courses hub read this list so a new page
 * cannot ship without an internal link and a sitemap URL.
 *
 * `updatedAt` is sitemap <lastmod> (YYYY-MM-DD). Bump it when that page's
 * copy changes. Do not bump it for a layout-only fix.
 */
export const SEO_PAGES = {
  deBangalore: {
    path: "/data-engineering-course-bangalore",
    title: "Data Engineering Course in Bengaluru (Whitefield): Live, Pay After Placement",
    description:
      "A live six-month Data Engineering course in Whitefield, Bengaluru. SQL, Python, Spark, Databricks, AWS, Azure and Airflow. Placement fee only after you accept an offer.",
    footerLabel: "Data Engineering, Bengaluru",
    priority: 0.9,
    updatedAt: "2026-10-06",
  },
  deIndia: {
    path: "/data-engineering-course-india",
    title: "Data Engineering Course in India: Live Online, Pay After Placement",
    description:
      "The same live six-month Data Engineering course, taught online across India, with counselling from the Whitefield, Bengaluru office. Placement fee only after an accepted offer.",
    footerLabel: "Data Engineering, India",
    priority: 0.85,
    updatedAt: "2026-10-05",
  },
  nonIt: {
    path: "/non-it-to-it",
    title: "Non-IT to IT: Which Course Fits a Career Switch",
    description:
      "Which BrowseJobs course fits a move from non-IT into IT — analytics, data engineering, DevOps, or Python — including career gaps. A job is not guaranteed.",
    footerLabel: "Non-IT to IT",
    priority: 0.8,
    updatedAt: "2026-10-06",
  },
  payAfter: {
    path: "/pay-after-placement",
    title: "Pay After Placement: How the BrowseJobs Fee Works",
    description:
      "₹30,000 registration after three free steps. The placement fee is your first three months' CTC, due only after you accept an offer. Nobody can guarantee a job.",
    footerLabel: "Pay after placement",
    priority: 0.8,
    updatedAt: "2026-10-05",
  },
  aiHiring: {
    path: "/ai-hiring",
    title: "AI Hiring Platform for Teams in India",
    description:
      "AI hiring for teams: structure the JD, rank applicants, run a role-specific AI interview, then read a written brief. Free for six months. You still make the hire.",
    footerLabel: "AI hiring",
    priority: 0.8,
    updatedAt: "2026-10-06",
  },
  aiInterview: {
    path: "/ai-interview-platform",
    title: "AI Interview Platform for Hiring Teams",
    description:
      "Role-specific AI interviews, scored only after grading, then a written brief. Camera checks are not captured. Automation never releases an offer.",
    footerLabel: "AI interview platform",
    priority: 0.75,
    updatedAt: "2026-10-06",
  },
} as const;

export type SeoPageKey = keyof typeof SEO_PAGES;

export const seoMoneyLinks = Object.values(SEO_PAGES);
