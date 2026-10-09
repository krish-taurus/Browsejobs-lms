import { ANSWERS_UPDATED } from "./answers";
import { EMPLOYERS_UPDATED } from "./employers";
import { FOUNDER_UPDATED } from "./entity";
import { GET_HIRED_UPDATED } from "./get-hired";
import { HOME_UPDATED } from "./home";

/**
 * Sitemap <lastmod> for public URLs whose copy is not a collection row.
 *
 * Dates are YYYY-MM-DD: the day that page's content last changed. They are
 * never the build time. Search engines ignore lastmod that moves on every
 * deploy.
 *
 * `deploy/deploy.sh` builds with `npm ci && npm run build` inside a full
 * git checkout (`git clone` / `git pull --ff-only`), so a git-log date
 * would be available on the VPS. The dates are written down anyway. CI
 * checkouts are often shallow, and a file's newest commit is often a
 * canonical tag or a layout fix rather than a copy change. Each value was
 * taken from the last commit that changed what a crawler reads.
 *
 * Bump the matching date in the same edit that changes the copy:
 *
 * - `/` — HOME_UPDATED in home.ts (homepage rewrite, 2026-10-06)
 * - `/courses` — this map (answers link on the hub, 2026-10-06)
 * - `/courses/[slug]` — CourseDetail.updatedAt in courses.ts
 * - `/masterclass` — this map (MasterclassView.tsx, 2026-07-19)
 * - `/employers` — EMPLOYERS_UPDATED in employers.ts (2026-10-06)
 * - `/get-hired` — GET_HIRED_UPDATED in get-hired.ts (2026-10-06)
 * - `/jobs` — this map. JobPosting markup changed 2026-10-05. Individual
 *   job records are not sitemap URLs, so API updated_at is not applied.
 * - `/brief` — this map (BriefView shell, 2026-07-19). The daily brief is
 *   client-fetched and gated; it is not its own sitemap URL.
 * - `/salaries` and `/salaries/*` — SALARIES_UPDATED in salaries.ts, or
 *   updatedAt on one row
 * - `/skills` and `/skills/*` — SKILLS_UPDATED in skills.ts, or updatedAt
 *   on one skill
 * - `/reviews` — this map (page copy, 2026-07-15). Review cards are
 *   client-fetched and are not separate sitemap URLs.
 * - SEO landing pages — updatedAt on the page in seo-nav.ts
 * - `/founder` — FOUNDER_UPDATED in entity.ts (2026-10-06)
 * - `/answers` and `/answers/*` — ANSWERS_UPDATED in answers.ts, or
 *   updatedAt on one answer (2026-10-06)
 */
export const PAGE_UPDATED = {
  "/": HOME_UPDATED,
  "/courses": "2026-10-06",
  "/masterclass": "2026-07-19",
  "/employers": EMPLOYERS_UPDATED,
  "/get-hired": GET_HIRED_UPDATED,
  "/jobs": "2026-10-05",
  "/brief": "2026-07-19",
  "/reviews": "2026-07-15",
  "/founder": FOUNDER_UPDATED,
  "/answers": ANSWERS_UPDATED,
} as const;

export type StaticSitemapPath = keyof typeof PAGE_UPDATED;

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/** Reject a build clock or a partial date before it reaches the sitemap. */
export function contentLastModified(path: string, value: string): string {
  if (!ISO_DATE.test(value)) {
    throw new Error(
      `Sitemap lastmod for ${path} must be YYYY-MM-DD, got ${JSON.stringify(value)}. Bump the content date (see src/content/last-modified.ts). Do not use the build time.`,
    );
  }
  return value;
}
