"use client";

import { useBranding } from "@/lib/branding";

/**
 * BrowseJobs wordmark — "The Breakout Bar" mark + Sora display type.
 *
 * Three blue strokes rising (interviews being listened to) and a fourth that
 * breaks out as an arrow — the trajectory the rebuilt syllabus creates.
 * Brand colours only; `tone="dark"` lightens the tile so it reads on ink
 * surfaces (footer/panels). Master asset: /public/logo.svg. Whitelabel tenants
 * override the name/logo via BrandingProvider; the default stays BrowseJobs.
 */
export function Mark({
  size = 32,
  tone = "light",
  className = "",
}: {
  size?: number;
  tone?: "light" | "dark";
  className?: string;
}) {
  const tile = tone === "dark" ? "#1B2A44" : "#0A1220";

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      fill="none"
      aria-hidden
      className={`shrink-0 ${className}`}
    >
      <rect width="64" height="64" rx="14" fill={tile} />
      <rect x="13.5" y="36" width="5.5" height="13" rx="2.75" fill="#1B6DF0" opacity="0.55" />
      <rect x="22.5" y="30" width="5.5" height="19" rx="2.75" fill="#1B6DF0" opacity="0.75" />
      <rect x="31.5" y="24" width="5.5" height="25" rx="2.75" fill="#1B6DF0" />
      <rect x="41.75" y="20" width="5.5" height="29" rx="2.75" fill="#FFFFFF" />
      <path
        d="M38.5 20.5 L44.5 13.5 L50.5 20.5"
        stroke="#FFFFFF"
        strokeWidth="5.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

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

  const brand = useBranding();
  const isDefault = brand.name === "BrowseJobs";

  return (
    <span className={`inline-flex items-center gap-2.5 ${className}`}>
      {brand.logo_url ? (
        // eslint-disable-next-line @next/next/no-img-element -- tenant logos are arbitrary remote URLs
        <img src={brand.logo_url} alt={brand.name} className="h-8 w-8 shrink-0 rounded-[8px] object-contain" />
      ) : (
        <Mark tone={tone} />
      )}
      <span className="display text-lg leading-none" style={{ color: ink }}>
        {brand.name}
        {showAi && isDefault && <span style={{ color: "var(--bj-trust)" }}>.ai</span>}
      </span>
    </span>
  );
}
