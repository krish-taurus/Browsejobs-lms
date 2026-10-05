/** Public origin for canonicals, sitemap entries, and structured-data URLs. */
export const SITE_ORIGIN = "https://browsejobs.ai";

/**
 * Absolute, self-referencing canonical. Query strings are dropped so tracking
 * variants (utm, gclid, fbclid) consolidate on the clean URL.
 */
export function canonical(path: string): string {
  if (path === "/" || path === "") return SITE_ORIGIN;
  const clean = path.startsWith("/") ? path : `/${path}`;
  return `${SITE_ORIGIN}${clean.split("?")[0]}`;
}
