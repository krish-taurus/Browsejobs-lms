/**
 * Interim BrowseJobs wordmark (design-system SVG, swappable for the final asset).
 *
 * The mark reads as the engine itself: three rising strokes (LISTEN) resolving into
 * a solid block (REBUILD) — the reverse-engineering story in one glyph. Crisp at any
 * size; `tone="dark"` inverts it for ink surfaces (footer/panels).
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
  const tile = tone === "dark" ? "#1B2A44" : "var(--bj-ink)";

  return (
    <span className={`inline-flex items-center gap-2.5 ${className}`}>
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden className="shrink-0">
        <rect width="32" height="32" rx="8" fill={tile} />
        {/* LISTEN: three rising strokes */}
        <rect x="8" y="18" width="2.6" height="6" rx="1.3" fill="#1B6DF0" />
        <rect x="12.4" y="14" width="2.6" height="10" rx="1.3" fill="#1B6DF0" opacity="0.85" />
        <rect x="16.8" y="10" width="2.6" height="14" rx="1.3" fill="#1B6DF0" opacity="0.7" />
        {/* REBUILD: the block it resolves into */}
        <rect x="21.4" y="8" width="3.4" height="16" rx="1.4" fill="#FFFFFF" opacity={tone === "dark" ? "0.9" : "0.92"} />
      </svg>
      <span className="display text-lg leading-none" style={{ color: ink }}>
        BrowseJobs
        {showAi && <span style={{ color: "var(--bj-trust)" }}>.ai</span>}
      </span>
    </span>
  );
}
