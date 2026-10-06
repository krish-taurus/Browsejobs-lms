/**
 * A same-site path to open after sign-in. Absolute URLs and protocol-relative
 * paths are dropped so a `next` query cannot send someone off the site.
 */
export function safeNextPath(value: string | null | undefined): string | null {
  if (!value) return null;
  const path = value.trim();
  if (path.length === 0 || path.length > 200) return null;
  if (!path.startsWith("/") || path.startsWith("//") || path.startsWith("/\\")) return null;
  if (/[\s\\]/.test(path) || path.includes("://")) return null;
  return path;
}
