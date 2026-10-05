"use client";

import { useId } from "react";

/**
 * The interviewer's face in the interview room — a small robot character
 * (dome head, antenna, glowing visor) with a distinct look for each phase,
 * replacing the plain "AI" glyph. Pure CSS/SVG: no image request, no added
 * bundle weight. The dark metallic head and cyan visor glow are the
 * character's own colouring, deliberately separate from the app's blue
 * (trust/deep) tokens used for the surrounding UI chrome and pulse halo —
 * the same layering a real product illustration uses: chrome one colour,
 * character glow another.
 */

export type AiAvatarPhase = "idle" | "listening" | "thinking" | "speaking";

const GLOW = "#22d3ee"; // cyan-400 — the character's own light.

export function AiAvatar({ phase }: { phase: AiAvatarPhase }) {
  const uid = useId();
  const headGradId = `ai-head-grad-${uid}`;
  const bodyGradId = `ai-body-grad-${uid}`;
  const glowId = `ai-glow-${uid}`;

  return (
    <div className="relative flex flex-col items-center">
      {/* Blue app-chrome halo, behind the character — thinking/speaking only. */}
      {(phase === "speaking" || phase === "thinking") && (
        <>
          <span className="absolute left-1/2 top-1/2 h-28 w-28 -translate-x-1/2 -translate-y-1/2 animate-ping rounded-full bg-trust/20 motion-reduce:animate-none sm:h-32 sm:w-32" />
          <span className="absolute left-1/2 top-1/2 h-20 w-20 -translate-x-1/2 -translate-y-1/2 animate-pulse rounded-full bg-trust/20 motion-reduce:animate-none sm:h-24 sm:w-24" />
        </>
      )}

      <svg viewBox="0 0 100 128" className="relative h-32 w-32 sm:h-40 sm:w-40" aria-hidden="true">
        <defs>
          <linearGradient id={headGradId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#2e3a56" />
            <stop offset="100%" stopColor="#0b0f1c" />
          </linearGradient>
          <linearGradient id={bodyGradId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#1b2436" />
            <stop offset="100%" stopColor="#060910" />
          </linearGradient>
          <filter id={glowId} x="-80%" y="-80%" width="260%" height="260%">
            <feGaussianBlur stdDeviation="2.2" result="blur" />
            <feMerge>
              <feMergeNode in="blur" />
              <feMergeNode in="SourceGraphic" />
            </feMerge>
          </filter>
        </defs>

        {/* Orbit ring — the character's own aura, spins while it thinks. */}
        <ellipse
          cx="50" cy="52" rx="46" ry="16"
          fill="none" stroke={GLOW} strokeOpacity="0.3" strokeWidth="1.5"
          className={phase === "thinking" ? "ai-ring-spin motion-reduce:animate-none" : undefined}
        />

        {/* Shoulders/body, peeking up from the bottom. */}
        <path d="M6 128 Q6 96 50 96 Q94 96 94 128 Z" fill={`url(#${bodyGradId})`} />
        <circle
          cx="50" cy="114" r="6" fill="none" stroke={GLOW} strokeWidth="2"
          className={phase === "idle" ? "animate-pulse motion-reduce:animate-none" : undefined}
          filter={`url(#${glowId})`}
        />

        {/* Antenna. */}
        <line x1="50" y1="14" x2="50" y2="2" stroke="#8b93a7" strokeWidth="2" strokeLinecap="round" />
        <circle
          cx="50" cy="2" r="3" fill={GLOW} filter={`url(#${glowId})`}
          className={`motion-reduce:animate-none ${phase === "thinking" ? "ai-antenna-blink" : "animate-pulse"}`}
        />

        {/* Head. */}
        <rect x="18" y="10" width="64" height="70" rx="32" fill={`url(#${headGradId})`} />
        {/* Ear accents. */}
        <circle cx="16" cy="48" r="5" fill="none" stroke={GLOW} strokeOpacity="0.5" strokeWidth="1.5" />
        <circle cx="84" cy="48" r="5" fill="none" stroke={GLOW} strokeOpacity="0.5" strokeWidth="1.5" />

        {/* Visor. */}
        <rect x="30" y="34" width="40" height="28" rx="14" fill="#050810" fillOpacity="0.6" />

        <g className={phase === "thinking" ? "ai-eye-scan motion-reduce:animate-none" : undefined}>
          <circle cx="42" cy="48" r="5.5" fill={GLOW} filter={`url(#${glowId})`} />
          <circle cx="58" cy="48" r="5.5" fill={GLOW} filter={`url(#${glowId})`} />
        </g>

        {phase === "speaking" ? (
          <g>
            {[0, 1, 2, 3].map((i) => (
              <rect
                key={i}
                x={37 + i * 7}
                y="57"
                width="4"
                height="8"
                rx="2"
                fill={GLOW}
                filter={`url(#${glowId})`}
                className="ai-eq-bar motion-reduce:animate-none"
                style={{ animationDelay: `${i * 0.12}s` }}
              />
            ))}
          </g>
        ) : (
          <rect
            x="42" y="59" width="16" height="3.5" rx="1.75"
            fill={GLOW} fillOpacity={phase === "listening" ? 1 : 0.75}
            filter={`url(#${glowId})`}
          />
        )}
      </svg>
    </div>
  );
}
