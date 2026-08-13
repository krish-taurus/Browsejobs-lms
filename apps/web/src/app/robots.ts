import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/seo";

/**
 * `/robots.txt` — previously a 404, which left the sitemap discoverable only
 * through Search Console.
 *
 * Crawling stays open everywhere a human can browse: the portal, admin and auth
 * routes are kept out of the index with `NO_INDEX` (see `@/lib/seo`) rather than
 * disallowed here, because Googlebot has to be able to fetch a page to see its
 * noindex. `/api/` is the one path with nothing to render and no reason to crawl.
 */
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: ["/api/"],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
    host: SITE_URL,
  };
}
