import { contact } from "@/content/landing";

/**
 * Public entity facts for Organization / Person JSON-LD.
 * Only values already published on the site. Missing facts stay in KRISH_NOTES
 * and are left off the structured data.
 */
export const founder = {
  name: "Dr Krish Bharggav",
  jobTitle: "Founder",
} as const;

/** No public founder page exists, so the Person node has no url until this is set. */
export const founderProfileUrl: string | null = null;

/**
 * Official profile URLs that are already real links in the footer or another
 * public surface. Empty on purpose: the footer does not link social profiles.
 */
export const officialProfileUrls: readonly string[] = [];

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

export const KRISH_NOTES = [
  "[Krish: the footer does not link LinkedIn, Instagram, YouTube, or Facebook. An older layout JSON-LD listed four profile URLs that are not linked anywhere else on the site, so sameAs is omitted. Add the official URLs to officialProfileUrls only after they are real links in the footer.]",
  "[Krish: there is no public founder page. The Person entity is Dr Krish Bharggav, Founder, with no url or sameAs. Set founderProfileUrl only if you want that URL on the public Person node.]",
  "[Krish: Data Analytics duration is published as “5–6 months”, so Course JSON-LD uses that text and does not invent a single ISO duration. Say which length to use if you want P5M or P6M.]",
] as const;

export const entityContact = contact;
