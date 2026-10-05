/**
 * The employer sidebar's own logo mark — three ascending bars in the
 * emerald palette, no background tile. The site-wide `Mark` in
 * components/brand/Wordmark.tsx (a dark tile + blue bars + breakout arrow)
 * is the real brand asset used everywhere else and stays untouched; this is
 * a same-motif, emerald-toned variant scoped to this one redesigned shell,
 * matching the approved kit references exactly (three plain bars, no tile,
 * no arrow).
 */
export function EmployerMark({ size = 28, className = "" }: { size?: number; className?: string }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 32 32"
      fill="none"
      aria-hidden
      className={`shrink-0 ${className}`}
    >
      <rect x="3" y="18" width="6" height="11" rx="2" fill="#9AC79A" />
      <rect x="13" y="11" width="6" height="18" rx="2" fill="#4E9B6E" />
      <rect x="23" y="4" width="6" height="25" rx="2" fill="var(--bj-dash-primary)" />
    </svg>
  );
}
