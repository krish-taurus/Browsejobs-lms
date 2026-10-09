import { useId } from "react";

/*
 * Decorative illustrations for the AI Interviews page. Colours come from the
 * theme tokens (fill-trust / stroke-trust / var(--bj-*)) so both themes and
 * whitelabel tenants follow automatically. Standalone copies with fixed
 * BrowseJobs colours live in public/illustrations/.
 */

// Waveform bar heights, outermost first; mirrored on both sides of the mic.
const BARS = [10, 20, 34, 50, 28, 42, 18];

/** Microphone in concentric rings with a waveform either side (300 × 260). */
export function MicIllustration({ className }: { className?: string }) {
  const id = useId().replace(/:/g, "");
  const core = `mic-core-${id}`;

  return (
    <svg viewBox="0 0 300 260" className={className} aria-hidden="true" focusable="false">
      <defs>
        <linearGradient id={core} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" style={{ stopColor: "var(--bj-trust)" }} />
          <stop offset="100%" style={{ stopColor: "var(--bj-deep)" }} />
        </linearGradient>
      </defs>

      {/* Thin concentric rings */}
      <circle cx="150" cy="130" r="118" fill="none" className="stroke-trust" strokeOpacity="0.12" strokeWidth="1.5" />
      <circle cx="150" cy="130" r="96" fill="none" className="stroke-trust" strokeOpacity="0.16" strokeWidth="1.5" />
      <circle cx="150" cy="130" r="76" fill="none" className="stroke-trust" strokeOpacity="0.2" strokeWidth="1.5" />

      {/* Soft translucent layers */}
      <circle cx="150" cy="130" r="72" className="fill-trust" fillOpacity="0.07" />
      <circle cx="150" cy="130" r="60" className="fill-trust" fillOpacity="0.12" />

      {/* Waveform, mirrored */}
      {BARS.map((h, i) => {
        const x = 12 + i * 11;
        const opacity = 0.25 + i * 0.1;
        return (
          <g key={i} className="stroke-trust" strokeWidth="4.5" strokeLinecap="round" strokeOpacity={opacity}>
            <line x1={x} y1={130 - h / 2} x2={x} y2={130 + h / 2} />
            <line x1={300 - x} y1={130 - h / 2} x2={300 - x} y2={130 + h / 2} />
          </g>
        );
      })}

      {/* Core circle */}
      <circle cx="150" cy="130" r="48" fill={`url(#${core})`} />
      <circle cx="150" cy="130" r="48" fill="none" stroke="white" strokeOpacity="0.18" strokeWidth="2" />

      {/* Microphone */}
      <g fill="none" stroke="white" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round">
        <rect x="139" y="98" width="22" height="40" rx="11" />
        <path d="M125 124v4a25 25 0 0 0 50 0v-4" />
        <path d="M150 153v12M139 165h22" />
      </g>

      {/* Accent dots */}
      <circle cx="232" cy="38" r="3.5" className="fill-trust" fillOpacity="0.55" />
      <circle cx="58" cy="78" r="3" className="fill-trust" fillOpacity="0.45" />
      <circle cx="208" cy="226" r="3" className="fill-trust" fillOpacity="0.5" />
    </svg>
  );
}

/** Two overlapping speech bubbles for empty interview lists (72 × 56). */
export function SpeechBubblesIllustration({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 72 56" className={className} aria-hidden="true" focusable="false">
      <path
        d="M30 4h30a10 10 0 0 1 10 10v14a10 10 0 0 1-10 10h-2v8l-9-8H30a10 10 0 0 1-10-10V14A10 10 0 0 1 30 4Z"
        className="fill-trust"
        fillOpacity="0.22"
      />
      <path
        d="M12 16h28a10 10 0 0 1 10 10v12a10 10 0 0 1-10 10H22l-9 7v-7h-1A10 10 0 0 1 2 38V26a10 10 0 0 1 10-10Z"
        className="fill-trust"
        fillOpacity="0.85"
      />
      <g className="fill-white">
        <circle cx="17" cy="32" r="2.4" />
        <circle cx="26" cy="32" r="2.4" />
        <circle cx="35" cy="32" r="2.4" />
      </g>
    </svg>
  );
}
