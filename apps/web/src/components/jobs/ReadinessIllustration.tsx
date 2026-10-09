import { useId } from "react";

/**
 * Profile card with a microphone badge (240 × 220) for the AI Readiness
 * banner. Colours come from theme tokens; a standalone copy with fixed
 * BrowseJobs colours lives at public/illustrations/ai-readiness-profile.svg.
 */
export function ReadinessIllustration({ className }: { className?: string }) {
  const badge = `readiness-badge-${useId().replace(/:/g, "")}`;

  return (
    <svg viewBox="0 0 240 220" className={className} aria-hidden="true" focusable="false">
      <defs>
        <linearGradient id={badge} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" style={{ stopColor: "var(--bj-trust)" }} />
          <stop offset="100%" style={{ stopColor: "var(--bj-deep)" }} />
        </linearGradient>
      </defs>

      {/* Soft rings */}
      <circle cx="122" cy="110" r="100" fill="none" className="stroke-trust" strokeOpacity="0.1" strokeWidth="1.5" />
      <circle cx="122" cy="110" r="80" fill="none" className="stroke-trust" strokeOpacity="0.16" strokeWidth="1.5" />
      <circle cx="122" cy="110" r="66" className="fill-trust" fillOpacity="0.06" />

      {/* Profile document */}
      <rect x="66" y="44" width="104" height="124" rx="14" className="fill-white stroke-trust" strokeOpacity="0.45" strokeWidth="1.6" />
      <circle cx="94" cy="76" r="15" className="fill-trust" fillOpacity="0.14" />
      <circle cx="94" cy="72" r="5.5" className="fill-trust" />
      <path d="M84 87a10 8 0 0 1 20 0" className="fill-trust" />
      <g className="fill-trust">
        <rect x="117" y="67" width="38" height="6" rx="3" fillOpacity="0.4" />
        <rect x="117" y="80" width="26" height="6" rx="3" fillOpacity="0.25" />
        <rect x="80" y="104" width="76" height="6" rx="3" fillOpacity="0.28" />
        <rect x="80" y="118" width="62" height="6" rx="3" fillOpacity="0.22" />
        <rect x="80" y="132" width="48" height="6" rx="3" fillOpacity="0.18" />
      </g>

      {/* Microphone badge */}
      <circle cx="166" cy="152" r="33" className="fill-trust" fillOpacity="0.12" />
      <circle cx="166" cy="152" r="26" fill={`url(#${badge})`} />
      <g fill="none" stroke="white" strokeWidth="2.8" strokeLinecap="round" strokeLinejoin="round">
        <rect x="160" y="138" width="12" height="20" rx="6" />
        <path d="M153.5 150v2a12.5 12.5 0 0 0 25 0v-2" />
        <path d="M166 164.5v5M160.5 169.5h11" />
      </g>

      {/* Accent diamonds */}
      <g className="fill-trust">
        <rect x="42" y="38" width="6" height="6" rx="1" transform="rotate(45 45 41)" fillOpacity="0.55" />
        <rect x="204" y="52" width="5" height="5" rx="1" transform="rotate(45 206.5 54.5)" fillOpacity="0.45" />
        <rect x="36" y="150" width="5" height="5" rx="1" transform="rotate(45 38.5 152.5)" fillOpacity="0.4" />
      </g>
    </svg>
  );
}
