"use client";

import { featureLabel } from "@/lib/locked-features";

/**
 * A pane of frosted glass over a page that is still being built.
 *
 * The screen underneath stays where it is — a student can see the shape of
 * what is coming — but nothing on it can be clicked, tabbed to or typed in.
 * That is the whole point of showing it rather than hiding it: the work is
 * visibly in progress, not missing.
 */
export function FeatureLockOverlay({ slug }: { slug: string }) {
  const label = featureLabel(slug);

  return (
    <div className="absolute inset-0 z-20 bg-paper/55 backdrop-blur-[2px]">
      <div className="sticky top-24 mx-auto flex max-w-sm justify-center px-4">
        <div className="rounded-2xl border border-line/80 bg-white/85 px-6 py-5 text-center shadow-soft backdrop-blur-sm">
          <div className="mx-auto grid h-10 w-10 place-items-center rounded-full bg-trust/10">
            <svg viewBox="0 0 24 24" className="h-5 w-5 text-trust" fill="none">
              <path
                d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </div>

          <p className="mono mt-3 text-[11px] uppercase tracking-widest text-muted">Work in progress</p>
          <p className="display mt-1 text-lg text-ink">{label} is locked</p>
          <p className="mt-1 text-sm text-muted">
            We&apos;re still building this page. It unlocks for you the moment it&apos;s ready.
          </p>
        </div>
      </div>
    </div>
  );
}
