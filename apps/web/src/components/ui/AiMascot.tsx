/**
 * A full original illustration — an AI mascot on a monitor, with a study
 * desk around it (books, a plant, a clock, a mug) — reused across the
 * empty states on Quizzes, Grades and Certificates so all three "nothing
 * here yet" moments read as one family. `variant` swaps what's on screen
 * and one desk prop, everything else stays put.
 */
export function AiMascot({
  variant,
  className = "h-56 w-full max-w-md",
}: {
  variant: "quiz" | "grade" | "certificate";
  className?: string;
}) {
  return (
    <div className={`relative mx-auto ${className}`}>
      <svg viewBox="0 0 400 240" aria-hidden="true" className="h-full w-full">
        {/* Ground shadow */}
        <ellipse cx="200" cy="222" rx="150" ry="10" className="fill-line/40" />

        {/* Monitor */}
        <rect x="108" y="26" width="184" height="126" rx="10" className="fill-white stroke-line" strokeWidth="2.5" />
        <rect x="120" y="38" width="160" height="90" rx="4" className="fill-sky" />
        <rect x="178" y="152" width="44" height="16" className="fill-line/60" />
        <rect x="160" y="168" width="80" height="8" rx="4" className="fill-line/70" />

        {/* Graduation cap, propped on the monitor corner. */}
        <g transform="translate(266 20) rotate(-8)">
          <path d="M0 0 24 7 0 14 -24 7Z" className="fill-ink" />
          <rect x="18.5" y="7" width="2.2" height="12" className="fill-ink" />
          <circle cx="19.6" cy="21" r="2.4" className="fill-amber" />
        </g>

        {/* Robot mascot, inside the screen. */}
        <g transform="translate(200 84)">
          <circle cx="-58" cy="8" r="5.5" className="fill-trust" />
          <circle cx="58" cy="8" r="5.5" className="fill-trust" />
          <rect x="-46" y="-24" width="92" height="62" rx="24" className="fill-trust" />
          <rect x="-30" y="-8" width="60" height="26" rx="13" className="fill-white" />
          <circle cx="-14" cy="5" r="6" className="fill-ink" />
          <circle cx="14" cy="5" r="6" className="fill-ink" />
          <path d="M-10 18q10 8 20 0" stroke="white" strokeWidth="3" strokeLinecap="round" fill="none" />
          {/* Waving arm */}
          <path d="M40 20q20-4 24-24" className="stroke-trust" strokeWidth="10" strokeLinecap="round" fill="none" />
          <circle cx="65" cy="-6" r="7" className="fill-trust" />
        </g>

        {/* Speech bubble, upper-left of the screen. */}
        <g transform="translate(134 46)">
          <rect width="34" height="22" rx="8" className="fill-white/90 stroke-line" strokeWidth="1.4" />
          <path d="M6 22l-5 7 9-7Z" className="fill-white" />
          <circle cx="9" cy="11" r="2.4" className="fill-trust/60" />
          <circle cx="17" cy="11" r="2.4" className="fill-trust/60" />
          <circle cx="25" cy="11" r="2.4" className="fill-trust/60" />
        </g>

        {variant === "quiz" && (
          <g transform="translate(232 44)">
            <rect width="36" height="24" rx="6" className="fill-ink" />
            <path d="M8 12l6 6 14-14" className="stroke-white" strokeWidth="2.6" fill="none" strokeLinecap="round" strokeLinejoin="round" />
          </g>
        )}
        {variant === "grade" && (
          <g transform="translate(232 44)">
            <rect width="36" height="24" rx="6" className="fill-ink" />
            <text x="18" y="17" textAnchor="middle" className="fill-white text-[13px] font-bold">A+</text>
          </g>
        )}
        {variant === "certificate" && (
          <g transform="translate(232 44)">
            <rect width="36" height="24" rx="6" className="fill-ink" />
            <path d="M9 8h18M9 13h18M9 18h10" stroke="white" strokeWidth="1.8" strokeLinecap="round" />
          </g>
        )}

        {/* Desk clutter around the monitor stand. */}
        <g transform="translate(56 150)">
          <ellipse cx="30" cy="62" rx="34" ry="8" className="fill-line/30" />
          <path d="M6 62V28c0-12 10-22 24-22s24 10 24 22v34" className="fill-verify/20" />
          <path d="M30 6c-3 6-3 12 0 16M22 10c-2 5-2 10 0 14M38 10c2 5 2 10 0 14" className="stroke-verify" strokeWidth="2.4" fill="none" strokeLinecap="round" />
          <rect x="6" y="56" width="48" height="10" rx="3" className="fill-deep" />
        </g>

        <g transform="translate(316 158)">
          <circle cx="26" cy="26" r="26" className="fill-amber/20" />
          <circle cx="26" cy="26" r="19" className="fill-white stroke-line" strokeWidth="2" />
          <path d="M26 15v11l7 5" className="stroke-ink" strokeWidth="2.2" strokeLinecap="round" fill="none" />
          <rect x="10" y="4" width="8" height="6" rx="2" className="fill-amber" />
          <rect x="34" y="4" width="8" height="6" rx="2" className="fill-amber" />
        </g>

        <g transform="translate(276 176)">
          <rect x="0" y="18" width="34" height="10" rx="2" className="fill-deep" />
          <rect x="3" y="8" width="28" height="10" rx="2" className="fill-verify" />
          <rect x="6" y="0" width="22" height="8" rx="2" className="fill-trust" />
        </g>

        <g transform="translate(150 186)">
          <path d="M0 0h20l-2 20a2 2 0 0 1-2 1.8H4A2 2 0 0 1 2 20Z" className="fill-amber" />
          <path d="M20 4h5a4 4 0 0 1 0 8h-5" className="stroke-amber" strokeWidth="2.2" fill="none" />
        </g>

        {/* Sparkles for a touch of life. */}
        <path d="M96 40l1.6 5.6L103 47l-5.4 1.4L96 54l-1.6-5.6L89 47l5.4-1.4Z" className="fill-amber" />
        <path d="M330 60l1.2 4 4 1.2-4 1.2-1.2 4-1.2-4-4-1.2 4-1.2Z" className="fill-trust/60" />

        {/* Quiz-only: loose marks floating around the scene, like a
            student's workings-out drifting off the page. */}
        {variant === "quiz" && (
          <>
            <text x="60" y="70" className="fill-verify/70 text-[34px] font-bold" style={{ fontFamily: "var(--font-display, inherit)" }}>?</text>
            <g transform="translate(340 110) rotate(10)">
              <circle r="16" className="fill-verify/15" />
              <path d="M-6 0l4 4 8-9" className="stroke-verify" strokeWidth="3" fill="none" strokeLinecap="round" strokeLinejoin="round" />
            </g>
            <text x="322" y="204" className="fill-trust/60 text-[22px] font-bold">%</text>
            <path d="M40 176q6-10 12 0t12 0" className="stroke-amber/70" strokeWidth="2.4" fill="none" strokeLinecap="round" />
          </>
        )}
      </svg>
    </div>
  );
}
