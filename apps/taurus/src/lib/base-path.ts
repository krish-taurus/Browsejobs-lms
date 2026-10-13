/**
 * Where the app is mounted (Sprint 058). Empty at the root (local dev,
 * Vercel); "/taurusai" when served at browsejobs.ai/taurusai behind the
 * BrowseJobs nginx. Set NEXT_PUBLIC_BASE_PATH at build time — next.config
 * reads the same variable.
 *
 * Next.js prefixes <Link>, redirect() and router.push itself. Everything else
 * that names an app path — a plain <a href>, fetch("/api/…"), a URL built from
 * an origin, a middleware redirect — goes through withBasePath().
 */
export const BASE_PATH = (process.env.NEXT_PUBLIC_BASE_PATH ?? "").replace(/\/+$/, "");

/** "/signup" → "/taurusai/signup". Leaves "#…", "mailto:", absolute and already-prefixed URLs alone. */
export function withBasePath(path: string): string {
  if (!BASE_PATH || !path.startsWith("/") || path.startsWith("//")) return path;
  if (path === BASE_PATH || path.startsWith(`${BASE_PATH}/`) || path.startsWith(`${BASE_PATH}?`)) return path;
  return `${BASE_PATH}${path}`;
}
