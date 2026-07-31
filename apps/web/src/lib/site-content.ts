/**
 * Server-side reader for the CRM-managed site content (hero copy, per-page
 * SEO) served by GET /api/v1/site-content. Every consumer keeps hardcoded
 * fallbacks, so an unreachable API can never blank the site.
 */

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export type SeoEntry = { title?: string; description?: string };

export type SiteContentMap = {
  hero?: Record<string, string>;
  seo?: Record<string, SeoEntry>;
} & Record<string, unknown>;

export async function getSiteContent(): Promise<SiteContentMap> {
  try {
    const res = await fetch(`${API_BASE}/api/v1/site-content`, {
      next: { revalidate: 60 },
    });
    if (!res.ok) return {};
    const body = (await res.json()) as { data?: SiteContentMap };

    return body.data ?? {};
  } catch {
    return {};
  }
}

/** SEO for a path, merged over the built-in fallback. */
export function seoFor(content: SiteContentMap, path: string, fallback: { title: string; description: string }) {
  const entry = content.seo?.[path];

  return {
    title: entry?.title || fallback.title,
    description: entry?.description || fallback.description,
  };
}

/**
 * Drop-in generateMetadata body for any public page: CRM-managed SEO for the
 * path, falling back to the page's built-in title/description.
 */
export async function cmsMetadata(path: string, fallback: { title: string; description: string }) {
  const content = await getSiteContent();
  const seo = seoFor(content, path, fallback);

  return {
    title: seo.title,
    description: seo.description,
    openGraph: { title: seo.title, description: seo.description },
  };
}
