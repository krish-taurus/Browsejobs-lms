/**
 * Builds the /thank-you URL every public lead form redirects to on success.
 *
 * Only the lead type and the course slug travel in the query string — never a
 * name, phone or email (spec §Privacy: no personal data in URLs, which would
 * otherwise leak into browser history, referrers and analytics).
 */
export function thankYouPath(leadType: string, courseSlug?: string | null): string {
  const params = new URLSearchParams({ type: leadType });
  if (courseSlug) params.set("course", courseSlug);
  return `/thank-you?${params.toString()}`;
}
