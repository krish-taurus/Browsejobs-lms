import type { Metadata } from "next";

/**
 * Single source for canonical URLs and social cards.
 *
 * Next merges `metadata` down the layout tree, and that inheritance breaks
 * OpenGraph in both directions: a page that declares no `openGraph` inherits
 * the root one verbatim (so every subpage claimed the home page's og:url and
 * og:title), while a page that declares one replaces the parent wholesale (so
 * the salary and skill pages silently lost og:image). `alternates` inherits the
 * same way, which is why a canonical must never be set in the root layout — it
 * would point the entire site at `/`.
 *
 * Every indexable page therefore builds its metadata here, and the root layout
 * carries only what is genuinely site-wide.
 */

/**
 * The apex host — no `www`, matching both the sitemap and the target of the
 * host-canonicalisation 301. Canonicals must never point at a URL that redirects.
 */
export const PRODUCTION_URL = "https://browsejobs.ai";

/**
 * `deploy.sh` sets `NEXT_PUBLIC_SITE_URL` to the apex host, but `.env` carries
 * `http://localhost:3000` for dev. A production build that picked that up would
 * emit `http://localhost:3000` canonicals across the whole site and deindex it,
 * so in production anything that is not an https origin is discarded.
 */
function resolveSiteUrl(): string {
  const configured = process.env.NEXT_PUBLIC_SITE_URL;
  if (!configured) return PRODUCTION_URL;
  if (process.env.NODE_ENV === "production" && !configured.startsWith("https://")) {
    return PRODUCTION_URL;
  }
  return configured;
}

export const SITE_URL = resolveSiteUrl();
export const SITE_NAME = "BrowseJobs";

export const SITE_TITLE = "BrowseJobs — Built from real interviews.";
export const SITE_DESCRIPTION =
  "This syllabus was not written. It was reverse-engineered — from up to ~50 real & mock interviews monitored daily. Three free steps before you pay anything; placement fee only after you accept an offer.";

const OG_IMAGE = {
  url: "/og.png",
  width: 1200,
  height: 630,
  alt: "BrowseJobs.ai — This syllabus was not written. It was reverse-engineered.",
};

/**
 * Auth forms, the student portal and the admin panel: app shells with nothing
 * to rank, plus token-scoped URLs (`/cv/shared/[token]`, `/verify/[code]`) that
 * must never surface in search. Deliberately *not* mirrored as a robots.txt
 * `Disallow` — a blocked URL can still be indexed from an inbound link, and a
 * crawler that cannot fetch the page never sees this noindex.
 */
export const NO_INDEX: Metadata = { robots: { index: false, follow: false } };

type PageSeo = {
  /** Page title without the "· BrowseJobs" suffix — the root template adds it. */
  title: string;
  description: string;
  /** Root-relative, leading slash, no trailing slash. The home page is "/". */
  path: string;
  /** Skip the title template, for pages whose title already names the brand. */
  absoluteTitle?: boolean;
};

export function pageMetadata({ title, description, path, absoluteTitle }: PageSeo): Metadata {
  const url = `${SITE_URL}${path === "/" ? "" : path}`;

  return {
    title: absoluteTitle ? { absolute: title } : title,
    description,
    alternates: { canonical: url },
    openGraph: {
      type: "website",
      siteName: SITE_NAME,
      title,
      description,
      url,
      images: [OG_IMAGE],
    },
    twitter: {
      card: "summary_large_image",
      title,
      description,
      images: [OG_IMAGE.url],
    },
  };
}
