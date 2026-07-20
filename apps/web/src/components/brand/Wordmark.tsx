/**
 * BrowseJobs wordmark — "The Upward Lens" mark + Sora display type.
 *
 * The mark is a magnifier whose lens holds a rising arrow, its handle on the
 * same 45° axis: browse = the lens, jobs = the trajectory. Brand colours only
 * (Trust blue → Deep navy tile, white strokes) so it sits on light and ink
 * surfaces unchanged. Master asset: /public/logo.svg.
 */
export function Wordmark({
  tone = "light",
  className = "",
  showAi = true,
}: {
  tone?: "light" | "dark";
  className?: string;
  showAi?: boolean;
}) {
  const ink = tone === "dark" ? "#FFFFFF" : "var(--bj-ink)";
  const gradId = tone === "dark" ? "bj-mark-dark" : "bj-mark-light";

  return (
    <span className={`inline-flex items-center gap-2.5 ${className}`}>
      <svg width="32" height="32" viewBox="0 0 64 64" fill="none" aria-hidden className="shrink-0">
        <defs>
          <linearGradient id={gradId} x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
            <stop stopColor="#1B6DF0" />
            <stop offset="1" stopColor="#0E3FA9" />
          </linearGradient>
        </defs>
        <rect width="64" height="64" rx="14" fill={`url(#${gradId})`} />
        <circle cx="28.5" cy="28.5" r="13.5" stroke="#FFFFFF" strokeWidth="5.5" />
        <path d="M39.5 39.5 L49.5 49.5" stroke="#FFFFFF" strokeWidth="6" strokeLinecap="round" />
        <path
          d="M22.5 34.5 L33 24 M25.5 22.9 H34.1 V31.5"
          stroke="#FFFFFF"
          strokeWidth="5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
      <span className="display text-lg leading-none" style={{ color: ink }}>
        BrowseJobs
        {showAi && <span style={{ color: "var(--bj-trust)" }}>.ai</span>}
      </span>
    </span>
  );
}
