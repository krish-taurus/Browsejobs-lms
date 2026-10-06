import { contact } from "@/content/landing";

/**
 * Public entity facts for Organization / Person JSON-LD.
 * Profile URLs are the ones linked from the footer or the founder page.
 * Press headlines are the titles on the article pages. No dates are added
 * unless that page published one.
 */
export const founder = {
  name: "Dr Krish Bharggav",
  jobTitle: "Founder",
} as const;

export const founderProfileUrl = "/founder";

/** BrowseJobs profiles. The Offer Letter accounts belong on the founder, not here. */
export const organizationSameAs = ["https://www.instagram.com/browsejobs.ai"] as const;

/** Krish's own profiles, including The Offer Letter, his related brand. */
export const founderSameAs = [
  "https://www.linkedin.com/in/dr-krish-bharggav-2072109a",
  "https://www.instagram.com/theofferletter6",
  "https://www.youtube.com/@theofferletter6",
] as const;

export type PressArticle = {
  publication: string;
  headline: string;
  url: string;
  /** ISO date from the article page. Omitted when the page did not publish one. */
  datePublished?: string;
};

export const pressCoverage: readonly PressArticle[] = [
  {
    publication: "Dainik Jagran",
    headline:
      "From Data Science to AI-Driven Recruitment: Dr. Krish Bharggav’s Work in the Changing Employment Landscape",
    url: "https://english.dainikjagranmpcg.com/business/from-data-science-to-ai-driven-recruitment-dr-krish-bharggav%E2%80%99s-work/article-34139",
    datePublished: "2026-09-28",
  },
  {
    publication: "Pioneer Edge",
    headline: "Technology, Skills and Careers: How Dr Krish Bharggav is bridging the employment gap",
    url: "https://pioneeredge.in/technology-skills-and-careers-how-dr-krish-bharggav-is-bridging-the-employment-gap/",
    datePublished: "2026-09-28",
  },
  {
    publication: "Oneindia",
    headline:
      "Krish Bharggav: Unlocking Technology, Talent & Entrepreneurship – A Deep Dive Into His Professional Journey",
    url: "https://www.oneindia.com/partner-content/dr-krish-bharggavs-journey-technology-talent-entrepreneurship-career-insights-012-8218647.html",
    datePublished: "2026-09-28",
  },
  {
    publication: "Ahmedabad Mirror",
    headline:
      "Dr. Krish Bharggav: Connecting Technology, Talent and Entrepreneurship Through AI-Powered Career Development",
    url: "https://www.ahmedabadmirror.com/dr-krish-bharggav-connecting-technology-talent-and-entrepreneurship-through-ai-powered-career-development/81922529.html",
  },
  {
    publication: "Bhaskar Digital",
    headline: "Dr. Krish Bharggav: Technology, Data Science and Entrepreneurship",
    url: "https://bhaskardigital.com/dr-krish-bharggav-technology-data-science-and-entrepreneurship/",
    datePublished: "2026-09-28",
  },
];

export const organizationDescription =
  "IBrowseJobs Technologies, Whitefield, Bengaluru. Data Engineering, DevOps and Cloud, Python Backend and Data Analytics courses, plus an AI interview for students and for hiring teams. The syllabus is reverse-engineered from real interviews. Nobody can guarantee employment — the market decides.";

export const LOGO_PATH = "/logo.svg";

export const publishedAddress = {
  streetAddress: "Whitefield",
  addressLocality: "Bengaluru",
  addressRegion: "Karnataka",
  postalCode: "560066",
  addressCountry: "IN",
} as const;

export const publishedHours = {
  dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
  opens: "09:00",
  closes: "19:00",
} as const;

export const entityContact = contact;
