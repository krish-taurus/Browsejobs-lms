/**
 * Features that are switched off for students while they are being finished.
 *
 * The page, its route and its place in the menu all stay exactly where they
 * are — opening one shows a "locked" panel in place of the screen. Nothing is
 * deleted, so unlocking is a config change rather than a code change.
 *
 * The list lives in LOCKED_FEATURES (apps/web/.env) as comma-separated first
 * path segments, e.g.
 *
 *   LOCKED_FEATURES=mentors,placement,jobs-for-you,cv
 *
 * It is read on the server for every request, so a restart of the web service
 * is enough to lock or unlock something — no rebuild.
 */

/** Human names for the pages we may lock; anything else falls back to its slug. */
const FEATURE_LABELS: Record<string, string> = {
  mentors: "Mentors",
  placement: "Placement",
  "jobs-for-you": "Jobs for You",
  cv: "My CV",
  interviews: "One to One Interviews",
  mock: "AI Interviews",
  store: "Store",
  tutor: "AI Tutor",
  certificates: "Certificates",
  reports: "Reports",
  pulse: "Pulse",
};

export function parseLockedFeatures(raw?: string | null): string[] {
  return (raw ?? "")
    .split(",")
    .map((entry) => entry.trim().replace(/^\/+/, "").toLowerCase())
    .filter(Boolean);
}

/**
 * The locked feature this path belongs to, or null. Matching is on the first
 * segment, so a locked "mentors" also covers /mentors/anything below it.
 */
export function findLockedFeature(pathname: string, locked: string[]): string | null {
  if (locked.length === 0) return null;

  const first = pathname.split("?")[0].split("/").filter(Boolean)[0]?.toLowerCase();

  return first && locked.includes(first) ? first : null;
}

export function featureLabel(slug: string): string {
  return FEATURE_LABELS[slug] ?? slug.replace(/-/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}
